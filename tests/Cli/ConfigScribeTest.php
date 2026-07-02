<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\ConfigFile;
use JesseGall\CodeCommandments\Cli\ConfigScribe;
use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Moment;
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

    public function test_ensure_plan_execution_injects_a_block_with_inferred_checks(): void
    {
        $scribe = $this->scribe();
        $scribe->scaffold(['app']);
        ConfigFile::inProject($this->dir)->disable('Demo\\Foo');

        $scribe->ensurePlanExecution(['composer test', 'composer lint']);

        $this->assertValidPhp();
        $this->assertSame(
            ['composer test', 'composer lint'],
            Config::load($this->dir)->planExecutionSettings()->checksFor(Moment::Complete),
        );
        // The human's own paths()/disable() lines are untouched.
        $this->assertSame(['app'], ConfigFile::inProject($this->dir)->paths());
        $this->assertSame(['Demo\\Foo'], ConfigFile::inProject($this->dir)->disabled());
    }

    public function test_ensure_plan_execution_is_idempotent(): void
    {
        $scribe = $this->scribe();
        $scribe->scaffold(['app']);
        $scribe->ensurePlanExecution(['composer test']);

        $before = (string) file_get_contents($this->configPath());
        $scribe->ensurePlanExecution(['composer something-else']); // a config with one already → no-op

        $this->assertSame($before, (string) file_get_contents($this->configPath()));
    }

    public function test_ensure_plan_execution_with_no_checks_still_declares_the_surface(): void
    {
        $scribe = $this->scribe();
        $scribe->scaffold(['app']);

        $scribe->ensurePlanExecution([]);

        $this->assertValidPhp();
        $this->assertSame([], Config::load($this->dir)->planExecutionSettings()->checksFor(Moment::Complete));

        // It counts as declared, so a later sync won't inject a second block.
        $before = (string) file_get_contents($this->configPath());
        $scribe->ensurePlanExecution(['composer test']);
        $this->assertSame($before, (string) file_get_contents($this->configPath()));
    }

    private function scribe(): ConfigScribe
    {
        return new ConfigScribe($this->configPath());
    }

    private function configPath(): string
    {
        return $this->dir . '/.commandments/config.php';
    }

    private function assertValidPhp(): void
    {
        exec('php -l ' . escapeshellarg($this->dir . '/.commandments/config.php') . ' 2>&1', $out, $status);
        $this->assertSame(0, $status, "config is not valid PHP:\n" . implode("\n", $out));
    }
}
