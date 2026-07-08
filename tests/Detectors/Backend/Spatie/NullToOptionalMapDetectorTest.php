<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\Spatie\NullToOptionalMapDetector;
use PHPUnit\Framework\TestCase;

final class NullToOptionalMapDetectorTest extends TestCase
{
    private const string PRELUDE = <<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Optional;
        class Position { public static function from($x): static { return new static(); } }
        PHP;

    public function test_flags_the_null_first_ternary_map(): void
    {
        $this->assertSame(1, $this->hits(<<<'PHP'
        class Maker {
            public function make($x) {
                return $x === null ? new Optional : Position::from($x);
            }
        }
        PHP));
    }

    public function test_flags_the_value_first_ternary_map(): void
    {
        $this->assertSame(1, $this->hits(<<<'PHP'
        class Maker {
            public function make($x) {
                return $x !== null ? Position::from($x) : new Optional();
            }
        }
        PHP));
    }

    public function test_flags_the_coalesce_map(): void
    {
        $this->assertSame(1, $this->hits(<<<'PHP'
        class Maker {
            public function make() {
                return $this->resolve() ?? new Optional;
            }
            private function resolve() { return null; }
        }
        PHP));
    }

    public function test_does_not_flag_a_new_optional_parameter_default(): void
    {
        // The CORRECT shape — a `T | Optional` slot defaulting to `new Optional`.
        $this->assertSame(0, $this->hits(<<<'PHP'
        final class Page {
            public function __construct(
                public readonly Position|Optional $position = new Optional(),
            ) {}
        }
        PHP));
    }

    public function test_does_not_flag_a_bare_return_of_new_optional(): void
    {
        $this->assertSame(0, $this->hits(<<<'PHP'
        class Maker {
            public function make(bool $absent) {
                if ($absent) { return new Optional(); }
                return Position::from([]);
            }
        }
        PHP));
    }

    public function test_does_not_flag_new_optional_passed_as_an_argument(): void
    {
        $this->assertSame(0, $this->hits(<<<'PHP'
        class Maker {
            public function make() {
                return Position::from(new Optional());
            }
        }
        PHP));
    }

    private function hits(string $body): int
    {
        return count((new NullToOptionalMapDetector)->find(Codebase::fromString(self::PRELUDE . $body, '/proj/app/File.php')));
    }
}
