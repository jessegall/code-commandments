<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\Spatie\PreferOptionalCreateDetector;
use PHPUnit\Framework\TestCase;

final class PreferOptionalCreateDetectorTest extends TestCase
{
    private const string PRELUDE = <<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Optional;
        class Coords { public static function from($x): static { return new static(); } }
        PHP;

    public function test_flags_new_optional_in_a_return(): void
    {
        $this->assertSame(1, $this->hits(<<<'PHP'
        class Maker {
            public function make(bool $absent) {
                if ($absent) { return new Optional(); }
                return Coords::from([]);
            }
        }
        PHP));
    }

    public function test_flags_new_optional_in_a_ternary_arm(): void
    {
        $this->assertSame(1, $this->hits(<<<'PHP'
        class Maker {
            public function make($x) {
                return $x === null ? new Optional : Coords::from($x);
            }
        }
        PHP));
    }

    public function test_does_not_flag_a_parameter_default(): void
    {
        // A static call is illegal as a default — `new Optional` must stay.
        $this->assertSame(0, $this->hits(<<<'PHP'
        final class Page {
            public function __construct(public readonly Coords|Optional $at = new Optional()) {}
        }
        PHP));
    }

    public function test_does_not_flag_a_property_default(): void
    {
        $this->assertSame(0, $this->hits(<<<'PHP'
        class Bag {
            public Coords|Optional $slot = new Optional();
        }
        PHP));
    }

    public function test_does_not_flag_construction_of_a_different_class(): void
    {
        $this->assertSame(0, $this->hits(<<<'PHP'
        class Maker {
            public function make() { return new Coords(); }
        }
        PHP));
    }

    private function hits(string $body): int
    {
        return count((new PreferOptionalCreateDetector)->find(Codebase::fromString(self::PRELUDE . $body, '/proj/app/File.php')));
    }
}
