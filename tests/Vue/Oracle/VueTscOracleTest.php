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

    public function test_it_resolves_every_components_unknowns_in_one_pass(): void
    {
        $widget = $this->component('Widget', 'const pageSizes = magic();');
        $panel = $this->component('Panel', 'const label = magic();');
        $runner = $this->runnerTyping(['pageSizes' => 'number[]', 'label' => 'string | null']);

        $types = new VueTscOracle($this->root, $runner)->resolveAll([
            $widget->path => ['sfc' => $widget, 'names' => ['pageSizes']],
            $panel->path => ['sfc' => $panel, 'names' => ['label']],
        ]);

        $this->assertSame(['pageSizes' => 'number[]'], $types[$widget->path]);
        $this->assertSame(['label' => 'string | null'], $types[$panel->path]);
        $this->assertSame(1, $runner->runs, 'the checker ran exactly ONCE for both components');
    }

    public function test_it_runs_vue_tsc_incrementally_with_lib_check_skipped(): void
    {
        $runner = $this->runnerTyping([]);
        $component = $this->component('Widget', 'const x = y();');

        new VueTscOracle($this->root, $runner)->resolveAll([$component->path => ['sfc' => $component, 'names' => ['x']]]);

        $this->assertStringEndsWith('/node_modules/.bin/vue-tsc', $runner->binary);
        $this->assertContains('--skipLibCheck', $runner->arguments);
        $this->assertContains('--incremental', $runner->arguments);
        $this->assertContains('--noEmit', $runner->arguments);
        $this->assertSame($this->root, $runner->cwd);
    }

    public function test_probe_files_are_written_beside_each_component_then_removed(): void
    {
        $runner = $this->runnerTyping([]);
        $component = $this->component('Widget', 'const x = y();');

        new VueTscOracle($this->root, $runner)->resolveAll([$component->path => ['sfc' => $component, 'names' => ['x']]]);

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

    private function component(string $name, string $body): Sfc
    {
        $source = "<script setup lang=\"ts\">\n{$body}\n</script>\n<template><div /></template>\n";
        $path = $this->root . "/src/{$name}.vue";
        file_put_contents($path, $source);

        return Sfc::parse($source, $path);
    }

    /**
     * A fake runner that plays vue-tsc: in ONE call it finds every probe our oracle wrote, and for
     * each emits a `TS2322` diagnostic per probed name it can type — exactly the shape (and file
     * attribution) {@see TscDiagnostics} and the oracle read.
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

            public int $runs = 0;

            public ?string $probeContents = null;

            /** @param array<string, string> $types */
            public function __construct(private readonly array $types) {}

            public function run(string $binary, array $arguments, string $cwd): string
            {
                $this->binary = $binary;
                $this->arguments = $arguments;
                $this->cwd = $cwd;
                $this->runs++;

                $lines = [];

                foreach (glob($cwd . '/src/__cc_probe_*.vue') ?: [] as $probe) {
                    $contents = (string) file_get_contents($probe);
                    $this->probeContents = $contents;

                    foreach ($this->types as $name => $type) {
                        if (str_contains($contents, TypeProbe::MARKER . $name)) {
                            $lines[] = basename($probe) . "(9,7): error TS2322: Type '{$type}' is not assignable to type '" . TypeProbe::MARKER . "{$name}'.";
                        }
                    }
                }

                return implode("\n", $lines);
            }
        };
    }
}
