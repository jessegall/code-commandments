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

    public function test_flags_the_preferred_optional_create_factory_form(): void
    {
        // The map reads the same whether the absent arm is `new Optional` or the preferred `Optional::create()`
        // — adopting the factory must not smuggle the sin past the detector.
        $this->assertSame(1, $this->hits(<<<'PHP'
        class Maker {
            public function make($x) {
                return $x === null ? Optional::create() : Position::from($x);
            }
        }
        PHP));

        $this->assertSame(1, $this->hits(<<<'PHP'
        class Maker {
            public function make() {
                return $this->resolve() ?? Optional::create();
            }
            private function resolve() { return null; }
        }
        PHP));
    }

    public function test_flags_the_is_null_guarded_ternary(): void
    {
        $this->assertSame(1, $this->hits(<<<'PHP'
        class Maker {
            public function make($x) {
                return is_null($x) ? Optional::create() : Position::from($x);
            }
        }
        PHP));
    }

    public function test_does_not_flag_a_boolean_guarded_ternary(): void
    {
        // A ternary whose condition is a BOOLEAN guard (not a null-check) is the idiomatic "omit an unloaded
        // relation" pattern, not a hand-rolled null→Optional map.
        $this->assertSame(0, $this->hits(<<<'PHP'
        class Maker {
            public function make($row) {
                return Position::from([
                    'range' => $row->relationLoaded('range') ? $row->range : Optional::create(),
                ]);
            }
        }
        PHP));
    }

    public function test_does_not_flag_the_shared_optional_or_missing_factory_home(): void
    {
        // The ONE named home the fix tells producers to create — it hydrates its OWN type via `static::from`,
        // so the map living here is correct, not a sin.
        $this->assertSame(0, $this->hits(<<<'PHP'
        trait OptionalOrMissing {
            public static function optionalOrMissing(mixed $payload): static|Optional {
                return $payload === null ? Optional::create() : static::from($payload);
            }
        }
        PHP));
    }

    private function hits(string $body): int
    {
        return count((new NullToOptionalMapDetector)->find(Codebase::fromString(self::PRELUDE . $body, '/proj/app/File.php')));
    }
}
