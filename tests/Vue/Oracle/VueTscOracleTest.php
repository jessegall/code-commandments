<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Vue\Oracle;

use JesseGall\CodeCommandments\Vue\Oracle\ProcessRunner;
use JesseGall\CodeCommandments\Vue\Oracle\TypeProbe;
use JesseGall\CodeCommandments\Vue\Oracle\VueTscOracle;
use JesseGall\CodeCommandments\Vue\Sfc;
use PHPUnit\Framework\TestCase;

final class VueTscOracleTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-oracle-' . uniqid();
        mkdir($this->root . '/src', 0777, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->root . '/src/*') ?: []);
        array_map('unlink', glob($this->root . '/.commandments/*') ?: []);
        @rmdir($this->root . '/src');
        @rmdir($this->root . '/.commandments');
        @rmdir($this->root);
    }

    public function test_it_resolves_unknown_locals_from_the_checkers_diagnostics(): void
    {
        $component = $this->component('const pageSizes = magic();');
        $runner = $this->runnerTyping(['pageSizes' => 'number[]']);

        $types = new VueTscOracle($this->root, $runner)->resolve($component, ['pageSizes']);

        $this->assertSame(['pageSizes' => 'number[]'], $types);
    }

    public function test_it_runs_vue_tsc_incrementally_with_lib_check_skipped(): void
    {
        $runner = $this->runnerTyping([]);

        new VueTscOracle($this->root, $runner)->resolve($this->component('const x = y();'), ['x']);

        $this->assertStringEndsWith('/node_modules/.bin/vue-tsc', $runner->binary);
        $this->assertContains('--skipLibCheck', $runner->arguments);
        $this->assertContains('--incremental', $runner->arguments);
        $this->assertContains('--noEmit', $runner->arguments);
        $this->assertSame($this->root, $runner->cwd);
    }

    public function test_the_probe_file_is_written_beside_the_component_then_removed(): void
    {
        $runner = $this->runnerTyping([]);

        new VueTscOracle($this->root, $runner)->resolve($this->component('const x = y();'), ['x']);

        $this->assertNotNull($runner->probeContents, 'the checker saw a probe file');
        $this->assertStringContainsString('__CcNo_x', (string) $runner->probeContents);
        $this->assertSame([], glob($this->root . '/src/__cc_probe_*.vue') ?: [], 'probe cleaned up');
    }

    public function test_availability_follows_the_vue_tsc_binary(): void
    {
        $this->assertFalse(VueTscOracle::available($this->root));

        mkdir($this->root . '/node_modules/.bin', 0777, true);
        touch($this->root . '/node_modules/.bin/vue-tsc');

        $this->assertTrue(VueTscOracle::available($this->root));

        unlink($this->root . '/node_modules/.bin/vue-tsc');
        @rmdir($this->root . '/node_modules/.bin');
        @rmdir($this->root . '/node_modules');
    }

    public function test_locate_walks_up_to_the_project_that_ships_vue_tsc(): void
    {
        $this->assertNull(VueTscOracle::locate($this->root . '/src'), 'no vue-tsc anywhere up the tree');

        mkdir($this->root . '/node_modules/.bin', 0777, true);
        touch($this->root . '/node_modules/.bin/vue-tsc');

        // From a nested scan path, the oracle is found at the ancestor that has the binary.
        $this->assertInstanceOf(VueTscOracle::class, VueTscOracle::locate($this->root . '/src'));

        unlink($this->root . '/node_modules/.bin/vue-tsc');
        @rmdir($this->root . '/node_modules/.bin');
        @rmdir($this->root . '/node_modules');
    }

    private function component(string $body): Sfc
    {
        $source = "<script setup lang=\"ts\">\n{$body}\n</script>\n<template><div /></template>\n";
        $path = $this->root . '/src/Widget.vue';
        file_put_contents($path, $source);

        return Sfc::parse($source, $path);
    }

    /**
     * A fake runner that plays vue-tsc: it finds the probe our oracle wrote, records the call, and
     * emits a `TS2322` diagnostic per name — exactly the shape {@see TscDiagnostics} reads.
     *
     * @param  array<string, string>  $types
     */
    private function runnerTyping(array $types): ProcessRunner
    {
        return new class($types) implements ProcessRunner {
            public string $binary = '';

            /** @var list<string> */
            public array $arguments = [];

            public string $cwd = '';

            public ?string $probeContents = null;

            /** @param array<string, string> $types */
            public function __construct(private readonly array $types) {}

            public function run(string $binary, array $arguments, string $cwd): string
            {
                $this->binary = $binary;
                $this->arguments = $arguments;
                $this->cwd = $cwd;

                $probe = glob($cwd . '/src/__cc_probe_*.vue')[0] ?? null;
                $base = $probe === null ? 'missing.vue' : basename($probe);
                $this->probeContents = $probe === null ? null : file_get_contents($probe);

                $lines = [];

                foreach ($this->types as $name => $type) {
                    $lines[] = "{$base}(9,7): error TS2322: Type '{$type}' is not assignable to type '" . TypeProbe::MARKER . "{$name}'.";
                }

                return implode("\n", $lines);
            }
        };
    }
}
