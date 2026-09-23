<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\CustomFile;
use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Tests\Concerns\TemporaryFolder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A project's own file that would take the process down if required. PHP decides a redeclaration and a
 * too-strict override at class-load and reports both as a FATAL no `try` can catch, so the only defence
 * is to recognise them before loading — every hook runs in one process, and one such file kills them all.
 */
final class CustomFileTest extends TestCase
{
    use TemporaryFolder;

    protected function setUp(): void
    {
        mkdir($this->root . '/custom');
    }

    private function write(string $name, string $php): string
    {
        $path = $this->root . '/custom/' . $name;
        file_put_contents($path, "<?php\n\nnamespace Probe;\n\n" . $php);

        return $path;
    }

    private function fileAt(string $path): CustomFile
    {
        return CustomFile::at($path, Codebase::scan($this->root . '/custom')->declarations());
    }

    /**
     * A class whose parent this process cannot see is not judged either — there is nothing to compare
     * against, and refusing on a guess would withhold a file that loads perfectly well.
     *
     * @return array<string, array{string, string}>
     */
    public static function faultlessFiles(): array
    {
        return [
            'an ordinary file' => ['Fine.php', 'final class Fine {}'],
            'a class with a parent this process cannot see' => ['Foreign.php', 'final class Foreign extends \\Some\\Vendor\\Thing {}'],
        ];
    }

    #[DataProvider('faultlessFiles')]
    public function test_a_file_with_nothing_wrong_loads(string $name, string $declaration): void
    {
        $path = $this->write($name, $declaration);

        $this->assertTrue($this->fileAt($path)->fault()->isNone());
    }

    /**
     * A worktree checks out its own copy of the custom folder, so one process can reach the same class
     * down two paths — and `require_once` keys on the path, not the name.
     */
    public function test_a_second_copy_of_a_loaded_class_is_refused(): void
    {
        // OUTSIDE the scanned folder — the two-roots case is precisely a copy the scan cannot see.
        $elsewhere = $this->root . '/other.php';
        file_put_contents($elsewhere, "<?php\n\nnamespace Probe;\n\nfinal class Twin {}\n");
        require $elsewhere;

        $path = $this->write('Twin.php', 'final class Twin {}');
        $fault = $this->fileAt($path)->fault();

        $this->assertTrue($fault->isSome());
        $this->assertStringContainsString('already declared by', $fault->unwrap());
        $this->assertStringContainsString($elsewhere, $fault->unwrap());
    }

    /**
     * `Custom::load()` runs several times in one process — the config, the agent catalog and the hook
     * registry each ask for it — and `require_once` makes every later pass a no-op. Reporting that would
     * print one line per file per load and bury the case that matters in its own noise.
     */
    public function test_the_same_file_loaded_again_is_not_a_fault(): void
    {
        $path = $this->write('Once.php', 'final class Once {}');

        $this->assertTrue($this->fileAt($path)->fault()->isNone(), 'not yet loaded');

        require $path;

        $this->assertTrue($this->fileAt($path)->fault()->isNone(), 'loaded from this very file — expected');
    }

    /**
     * The exact fatal a consumer hit: a hook declaring a moment handler `private` where the base declares
     * it `protected`.
     */
    public function test_an_override_stricter_than_its_parent_is_refused(): void
    {
        $base = Hook::class;
        $path = $this->write('Strict.php', <<<PHP
            final class Strict extends \\{$base}
            {
                public function bindings(): array
                {
                    return [];
                }

                private function onPreCompact(\$event): int
                {
                    return 0;
                }
            }
            PHP);

        $fault = $this->fileAt($path)->fault();

        $this->assertTrue($fault->isSome());
        $this->assertStringContainsString('onPreCompact', $fault->unwrap());
        $this->assertStringContainsString('may not be stricter', $fault->unwrap());
    }

    /**
     * Widening is legal — a public override of a protected method compiles fine, and must not be refused.
     */
    public function test_a_wider_override_is_allowed(): void
    {
        $base = Hook::class;
        $path = $this->write('Wide.php', <<<PHP
            final class Wide extends \\{$base}
            {
                public function bindings(): array
                {
                    return [];
                }

                public function onPreCompact(\$event): int
                {
                    return 0;
                }
            }
            PHP);

        $this->assertTrue($this->fileAt($path)->fault()->isNone());
    }
}
