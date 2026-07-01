<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\ConfigFile;
use JesseGall\CodeCommandments\Cli\ConfigScribe;
use PHPUnit\Framework\TestCase;

/**
 * {@see ConfigScribe} writes `.commandments/config.php` — scaffolding a fresh one with the roots
 * baked into `paths()`, filling an empty `paths()`, and overwriting it on reindex — always through
 * the AST, leaving the rest of the file untouched, and always valid PHP.
 */
final class ConfigScribeTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/cc-scribe-' . uniqid('', true);
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    public function test_scaffold_bakes_the_roots_into_paths(): void
    {
        $scribe = $this->scribe();

        $this->assertTrue($scribe->scaffold(['app', 'src']));
        $this->assertFalse($scribe->scaffold(['other']), 'written once');

        $this->assertSame(['app', 'src'], ConfigFile::inProject($this->dir)->paths());
        $this->assertValidPhp();
    }

    public function test_ensure_paths_fills_an_empty_call_but_not_a_populated_one(): void
    {
        $scribe = $this->scribe();
        $scribe->scaffold([]); // empty paths()

        $scribe->ensurePaths(['app', 'src']);
        $this->assertSame(['app', 'src'], ConfigFile::inProject($this->dir)->paths());

        $scribe->ensurePaths(['nope']); // already populated → no-op
        $this->assertSame(['app', 'src'], ConfigFile::inProject($this->dir)->paths());
        $this->assertValidPhp();
    }

    public function test_rewrite_paths_overwrites_and_preserves_the_rest(): void
    {
        $scribe = $this->scribe();
        $scribe->scaffold(['app']);
        ConfigFile::inProject($this->dir)->disable('Demo\\Foo');

        $scribe->rewritePaths(['app', 'src', 'modules']);

        $this->assertSame(['app', 'src', 'modules'], ConfigFile::inProject($this->dir)->paths());
        $this->assertSame(['Demo\\Foo'], ConfigFile::inProject($this->dir)->disabled(), 'disable() survived the reindex');
        $this->assertValidPhp();
    }

    private function scribe(): ConfigScribe
    {
        return new ConfigScribe($this->dir . '/.commandments/config.php');
    }

    private function assertValidPhp(): void
    {
        exec('php -l ' . escapeshellarg($this->dir . '/.commandments/config.php') . ' 2>&1', $out, $status);
        $this->assertSame(0, $status, "config is not valid PHP:\n" . implode("\n", $out));
    }
}
