<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\Spatie\HookMissingComputedDetector;
use JesseGall\CodeCommandments\Scribes\Backend\HookMissingComputedScribe;
use PHPUnit\Framework\TestCase;

final class HookMissingComputedDetectorTest extends TestCase
{
    private const string PRELUDE = <<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        use Spatie\LaravelData\Attributes\Computed;
        use Spatie\LaravelData\Attributes\WithCast;
        PHP;

    public function test_flags_a_get_hook_without_computed_on_a_data_class(): void
    {
        $this->assertSame(1, $this->hits(<<<'PHP'
        final class Shell extends Data {
            public array $docks { get => $this->all(); }
            public function __construct(private array $items = []) {}
            private function all(): array { return $this->items; }
        }
        PHP));
    }

    public function test_does_not_flag_a_hook_already_marked_computed(): void
    {
        $this->assertSame(0, $this->hits(<<<'PHP'
        final class Shell extends Data {
            #[Computed]
            public array $docks { get => $this->all(); }
            public function __construct(private array $items = []) {}
            private function all(): array { return $this->items; }
        }
        PHP));
    }

    public function test_does_not_flag_a_hook_on_a_non_data_class(): void
    {
        $this->assertSame(0, $this->hits(<<<'PHP'
        final class Widget {
            public array $docks { get => $this->all(); }
            public function __construct(private array $items = []) {}
            private function all(): array { return $this->items; }
        }
        PHP));
    }

    public function test_does_not_flag_a_get_and_set_property(): void
    {
        // A property with a `set` hook is a real read-write field Spatie can hydrate — not a computed value.
        $this->assertSame(0, $this->hits(<<<'PHP'
        final class Shell extends Data {
            public string $slug { get => $this->raw; set => $this->raw = strtolower($value); }
            public function __construct(private string $raw = '') {}
        }
        PHP));
    }

    public function test_does_not_flag_a_plain_property(): void
    {
        $this->assertSame(0, $this->hits(<<<'PHP'
        final class Shell extends Data {
            public function __construct(public readonly array $docks) {}
        }
        PHP));
    }

    public function test_the_scribe_stamps_computed_below_existing_attributes_and_imports_it(): void
    {
        $php = "<?php\nnamespace App;\nuse Spatie\\LaravelData\\Data;\nuse Spatie\\LaravelData\\Attributes\\WithCast;\n"
            . "final class Shell extends Data {\n"
            . "    #[WithCast(SomeCast::class)]\n"
            . "    public array \$docks { get => \$this->all(); }\n"
            . "    private function all(): array { return []; }\n"
            . "}\n";

        $codebase = Codebase::fromString($php, '/proj/app/Shell.php');
        $rewrites = new HookMissingComputedScribe()->rewrite((new HookMissingComputedDetector)->find($codebase));
        $fixed = reset($rewrites);

        $this->assertStringContainsString("#[WithCast(SomeCast::class)]\n    #[Computed]\n    public array \$docks", $fixed);
        $this->assertStringContainsString('use Spatie\LaravelData\Attributes\Computed;', $fixed);
    }

    private function hits(string $body): int
    {
        return count((new HookMissingComputedDetector)->find(Codebase::fromString(self::PRELUDE . $body, '/proj/app/File.php')));
    }
}
