<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Support;

use JesseGall\CodeCommandments\Support\Binary;
use PHPUnit\Framework\TestCase;

/**
 * Where a project's `commandments` executable is, for every command we write into that project — a
 * hook, a plan check, an instruction. It used to be the literal `vendor/bin/commandments` everywhere,
 * which is right for a consumer and wrong for the one project that must judge itself: composer never
 * shims a package's own bin into its own vendor, so every wired hook here failed on each tool call.
 */
final class BinaryTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-binary-' . uniqid('', true);
        @mkdir($this->root, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_a_consumer_gets_composers_shim(): void
    {
        $this->give('vendor/bin/commandments');

        $this->assertSame('vendor/bin/commandments', Binary::in($this->root));
    }

    public function test_a_checkout_that_carries_its_own_executable_gets_that_one(): void
    {
        $this->give('bin/commandments');

        $this->assertSame('bin/commandments', Binary::in($this->root));
    }

    public function test_the_shim_wins_when_a_project_has_both(): void
    {
        // A consumer that also happens to keep a `bin/` — the shim is the one composer maintains.
        $this->give('bin/commandments');
        $this->give('vendor/bin/commandments');

        $this->assertSame('vendor/bin/commandments', Binary::in($this->root));
    }

    public function test_a_project_with_nothing_installed_is_told_the_path_it_will_have(): void
    {
        // Wiring can run before the first `composer install`; naming the shim means the command
        // works the moment there is one, rather than being wrong forever.
        $this->assertSame('vendor/bin/commandments', Binary::in($this->root));
    }

    public function test_the_path_is_relative_so_it_survives_the_project_moving(): void
    {
        $this->give('bin/commandments');

        // A hook is anchored at $CLAUDE_PROJECT_DIR and a check runs from the project root; an
        // absolute path here would bake in one machine's checkout.
        $this->assertStringNotContainsString($this->root, Binary::in($this->root));
    }

    private function give(string $path): void
    {
        @mkdir("{$this->root}/" . dirname($path), 0777, true);
        touch("{$this->root}/{$path}");
    }
}
