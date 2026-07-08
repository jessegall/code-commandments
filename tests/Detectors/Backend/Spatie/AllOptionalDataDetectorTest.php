<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\Spatie\AllOptionalDataDetector;
use PHPUnit\Framework\TestCase;

final class AllOptionalDataDetectorTest extends TestCase
{
    private const string PRELUDE = <<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        use Spatie\LaravelData\Optional;
        PHP;

    public function test_flags_a_data_class_where_every_field_is_optional(): void
    {
        $this->assertSame(1, $this->hits(<<<'PHP'
        final class Grid extends Data {
            public function __construct(
                public readonly int|Optional $columns = new Optional(),
                public readonly int|Optional $span = new Optional(),
            ) {}
        }
        PHP));
    }

    public function test_does_not_flag_when_one_field_is_required(): void
    {
        // A required core field is the object's identity — not the all-optional smell.
        $this->assertSame(0, $this->hits(<<<'PHP'
        final class Money extends Data {
            public function __construct(
                public readonly int $amount,
                public readonly string|Optional $currency = new Optional(),
            ) {}
        }
        PHP));
    }

    public function test_does_not_flag_concrete_defaults(): void
    {
        // The RIGHT shape — concrete leaves, no Optional. Nothing to flag.
        $this->assertSame(0, $this->hits(<<<'PHP'
        final class Grid extends Data {
            public function __construct(
                public readonly int $columns = 1,
                public readonly int $span = 1,
            ) {}
        }
        PHP));
    }

    public function test_does_not_flag_an_all_nullable_class(): void
    {
        // `?T = null` is the all-NULLABLE sin, a different detector — this one is only for `T|Optional`.
        $this->assertSame(0, $this->hits(<<<'PHP'
        final class Filters extends Data {
            public function __construct(
                public readonly ?string $status = null,
                public readonly ?string $assignee = null,
            ) {}
        }
        PHP));
    }

    public function test_does_not_flag_a_non_data_class(): void
    {
        $this->assertSame(0, $this->hits(<<<'PHP'
        final class Grid {
            public function __construct(
                public readonly int|Optional $columns = new Optional(),
            ) {}
        }
        PHP));
    }

    private function hits(string $body): int
    {
        return count((new AllOptionalDataDetector)->find(Codebase::fromString(self::PRELUDE . $body, '/proj/app/File.php')));
    }
}
