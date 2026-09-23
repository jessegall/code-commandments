<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Support;

use JesseGall\CodeCommandments\Support\Binary;
use JesseGall\CodeCommandments\Tests\Concerns\TemporaryFolder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Where a project's `commandments` executable is, for every command we write into that project — a
 * hook, a plan check, an instruction: composer's shim in a consumer, and the checkout's own `bin/` in
 * the package itself, since composer never shims a package's own bin into its own vendor.
 */
final class BinaryTest extends TestCase
{
    use TemporaryFolder;

    /**
     * @return array<string, array{string}>
     */
    public static function installedExecutables(): array
    {
        return [
            'a consumer gets composer\'s shim' => ['vendor/bin/commandments'],
            'a checkout that carries its own executable gets that one' => ['bin/commandments'],
        ];
    }

    #[DataProvider('installedExecutables')]
    public function test_the_one_executable_a_project_has_is_the_one_named(string $executable): void
    {
        $this->give($executable);

        $this->assertSame($executable, Binary::in($this->root));
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
