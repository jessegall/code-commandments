<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\Spatie\NullableWireObjectDetector;
use PHPUnit\Framework\TestCase;

final class NullableWireObjectDetectorTest extends TestCase
{
    private const string PRELUDE = <<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        use Spatie\LaravelData\Optional;
        use Spatie\TypeScriptTransformer\Attributes\TypeScript;
        enum Status: string { case Draft = 'draft'; }
        class Chrome extends Data { public function __construct(public readonly string $style = '') {} }
        PHP;

    public function test_flags_a_nullable_nested_data_on_a_typescript_class(): void
    {
        $this->assertSame(1, $this->hits(<<<'PHP'
        #[TypeScript]
        final class Element extends Data {
            public function __construct(
                public readonly string $id,
                public readonly Chrome|null $chrome = null,
            ) {}
        }
        PHP));
    }

    public function test_flags_a_nullable_enum_too(): void
    {
        $this->assertSame(1, $this->hits(<<<'PHP'
        #[TypeScript]
        final class Element extends Data {
            public function __construct(
                public readonly string $id,
                public readonly ?Status $status = null,
            ) {}
        }
        PHP));
    }

    public function test_does_not_flag_the_optional_shape(): void
    {
        $this->assertSame(0, $this->hits(<<<'PHP'
        #[TypeScript]
        final class Element extends Data {
            public function __construct(
                public readonly string $id,
                public readonly Chrome|Optional $chrome = new Optional(),
            ) {}
        }
        PHP));
    }

    public function test_does_not_flag_a_nullable_scalar(): void
    {
        // A scalar `| null` can legitimately mean explicit null — left alone.
        $this->assertSame(0, $this->hits(<<<'PHP'
        #[TypeScript]
        final class Element extends Data {
            public function __construct(
                public readonly string $id,
                public readonly string|null $label = null,
            ) {}
        }
        PHP));
    }

    public function test_does_not_flag_when_the_class_is_not_typescript(): void
    {
        // Not frontend-bound — the wire shape doesn't matter, so null is fine.
        $this->assertSame(0, $this->hits(<<<'PHP'
        final class Element extends Data {
            public function __construct(
                public readonly string $id,
                public readonly Chrome|null $chrome = null,
            ) {}
        }
        PHP));
    }

    private function hits(string $body): int
    {
        return count((new NullableWireObjectDetector)->find(Codebase::fromString(self::PRELUDE . $body, '/proj/app/File.php')));
    }
}
