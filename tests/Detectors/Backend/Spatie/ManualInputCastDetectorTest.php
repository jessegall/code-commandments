<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\Spatie\ManualInputCastDetector;
use PHPUnit\Framework\TestCase;

final class ManualInputCastDetectorTest extends TestCase
{
    /**
     * @return list<?string>  the enclosing-class scope of every finding
     */
    private function scopes(string $php): array
    {
        $matches = new ManualInputCastDetector()->find(Codebase::fromString($php));

        return array_map(static fn ($m): ?string => $m->scope(), $matches);
    }

    public function test_flags_a_property_hand_built_in_every_from_source(): void
    {
        $scopes = $this->scopes(<<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        final class Money { public function __construct(public int $cents, public string $code) {} }
        final class OrderData extends Data {
            public function __construct(public readonly Money $price) {}
        }
        final class Caller {
            public function a(): OrderData { return OrderData::from(['price' => new Money(100, 'EUR')]); }
            public function b(array $raw): OrderData {
                $data = ['price' => new Money($raw['c'], $raw['k'])];
                return OrderData::from($data);
            }
        }
        PHP);

        $this->assertSame(['App\\OrderData::__construct'], $scopes);
    }

    public function test_flags_a_named_constructor_factory_at_self_from_sites(): void
    {
        // The real workflows shape: self::from(...) named constructors, the value built by a factory on
        // the value object's own class (X::coalesce(): self) and by `new X` — both hand-built.
        $scopes = $this->scopes(<<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        final class ValueBag { public static function coalesce(mixed $v): self { return new self; } }
        final class LlmResult extends Data {
            public function __construct(public readonly ValueBag $structured) {}
            public static function failure(): self { return self::from(['structured' => new ValueBag]); }
            public static function ok($s): self { return self::from(['structured' => ValueBag::coalesce($s)]); }
        }
        PHP);

        $this->assertSame(['App\\LlmResult::__construct'], $scopes);
    }

    public function test_does_not_flag_a_native_date_type_built_at_the_call_site(): void
    {
        // A Carbon is cast natively (DateTimeInterfaceCast); building one at the call site needs no cast.
        $scopes = $this->scopes(<<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        final class EventData extends Data {
            public function __construct(public readonly \Carbon\CarbonImmutable $at) {}
            public static function now(): self { return self::from(['at' => \Carbon\CarbonImmutable::parse('now')]); }
        }
        PHP);

        $this->assertSame([], $scopes);
    }

    public function test_does_not_flag_when_a_from_source_passes_the_value_through_cleanly(): void
    {
        $scopes = $this->scopes(<<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        final class Money { public function __construct(public int $cents, public string $code) {} }
        final class OrderData extends Data {
            public function __construct(public readonly Money $price) {}
        }
        final class Caller {
            public function a(): OrderData { return OrderData::from(['price' => new Money(1, 'EUR')]); }
            public function b(Money $ready): OrderData { return OrderData::from(['price' => $ready]); }
        }
        PHP);

        $this->assertSame([], $scopes);
    }

    public function test_does_not_flag_when_built_from_a_model_via_from(): void
    {
        // Opaque ::from($model) — Spatie auto-maps; nothing hand-built.
        $scopes = $this->scopes(<<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        final class Money { public function __construct(public int $cents, public string $code) {} }
        final class OrderData extends Data {
            public function __construct(public readonly Money $price) {}
        }
        final class Caller {
            public function a($model): OrderData { return OrderData::from($model); }
        }
        PHP);

        $this->assertSame([], $scopes);
    }

    public function test_does_not_flag_a_property_only_ever_new_constructed(): void
    {
        // A cast fires on ::from(), never plain `new` — so a `new`-only property is not a cast case.
        $scopes = $this->scopes(<<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        final class Money { public function __construct(public int $cents, public string $code) {} }
        final class OrderData extends Data {
            public function __construct(public readonly Money $price) {}
        }
        final class Caller {
            public function a(): OrderData { return new OrderData(new Money(1, 'EUR')); }
        }
        PHP);

        $this->assertSame([], $scopes);
    }

    public function test_does_not_flag_a_property_that_already_has_a_cast(): void
    {
        $scopes = $this->scopes(<<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        use Spatie\LaravelData\Attributes\WithCast;
        final class Money { public function __construct(public int $cents, public string $code) {} }
        final class MoneyCast {}
        final class OrderData extends Data {
            public function __construct(#[WithCast(MoneyCast::class)] public readonly Money $price) {}
        }
        final class Caller {
            public function a(): OrderData { return OrderData::from(['price' => new Money(1, 'EUR')]); }
        }
        PHP);

        $this->assertSame([], $scopes);
    }

    public function test_does_not_flag_a_nested_data_property(): void
    {
        // A nested Data is auto-hydrated — no cast needed even if constructed inline.
        $scopes = $this->scopes(<<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        final class LineData extends Data { public function __construct(public int $qty) {} }
        final class CartData extends Data {
            public function __construct(public readonly LineData $line) {}
        }
        final class Caller {
            public function a(): CartData { return CartData::from(['line' => new LineData(2)]); }
        }
        PHP);

        $this->assertSame([], $scopes);
    }

    public function test_does_not_flag_a_scalar_property(): void
    {
        $scopes = $this->scopes(<<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        final class OrderData extends Data {
            public function __construct(public readonly string $ref) {}
        }
        final class Caller {
            public function a(): OrderData { return OrderData::from(['ref' => strtoupper('x')]); }
        }
        PHP);

        $this->assertSame([], $scopes);
    }
}
