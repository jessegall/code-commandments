<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\Spatie\ManualOutputTransformDetector;
use PHPUnit\Framework\TestCase;

final class ManualOutputTransformDetectorTest extends TestCase
{
    /**
     * @return list<string>  the enclosing-class scope of every finding
     */
    private function scopes(string $php): array
    {
        $matches = new ManualOutputTransformDetector()->find(Codebase::fromString($php));

        return array_map(static fn ($m): ?string => $m->scope(), $matches);
    }

    public function test_flags_a_getter_that_flattens_a_value_object_into_an_array(): void
    {
        $scopes = $this->scopes(<<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        final class Money { public function __construct(public int $cents, public string $code) {} }
        final class PriceData extends Data {
            public function __construct(public readonly Money $money) {}
            public array $priceInEuro { get => ['amount' => $this->money->cents, 'currency' => $this->money->code]; }
        }
        PHP);

        $this->assertSame(['App\\PriceData'], $scopes);
    }

    public function test_flags_a_computed_method_that_flattens_a_value_object(): void
    {
        $scopes = $this->scopes(<<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        use Spatie\LaravelData\Attributes\Computed;
        final class Money { public function __construct(public int $cents, public string $code) {} }
        final class InvoiceData extends Data {
            public function __construct(public readonly Money $money) {}
            #[Computed]
            public function total(): array { return ['amount' => $this->money->cents, 'currency' => $this->money->code]; }
        }
        PHP);

        $this->assertSame(['App\\InvoiceData::total'], $scopes);
    }

    public function test_flags_a_constructor_assignment_of_a_flattened_value_object_to_a_public_slot(): void
    {
        $scopes = $this->scopes(<<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        final class Money { public function __construct(public int $cents, public string $code) {} }
        final class OrderPage extends Data {
            public array $priceInEuro;
            public function __construct(Money $money) {
                $this->priceInEuro = ['amount' => $money->cents, 'currency' => $money->code];
            }
        }
        PHP);

        $this->assertNotSame([], $scopes);
    }

    public function test_does_not_flag_a_constructor_assignment_to_a_private_property(): void
    {
        // A private slot isn't serialized output — not this sin.
        $scopes = $this->scopes(<<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        final class Money { public function __construct(public int $cents, public string $code) {} }
        final class OrderPage extends Data {
            private array $cache;
            public function __construct(Money $money) {
                $this->cache = ['amount' => $money->cents, 'currency' => $money->code];
            }
        }
        PHP);

        $this->assertSame([], $scopes);
    }

    public function test_does_not_flag_a_plain_method_returning_an_array_without_computed(): void
    {
        // No #[Computed] and not a hook — not a serialized slot, so not this sin.
        $scopes = $this->scopes(<<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        final class Money { public function __construct(public int $cents, public string $code) {} }
        final class ReceiptData extends Data {
            public function __construct(public readonly Money $money) {}
            public function debug(): array { return ['amount' => $this->money->cents, 'currency' => $this->money->code]; }
        }
        PHP);

        $this->assertSame([], $scopes);
    }

    public function test_does_not_flag_a_getter_that_composes_the_objects_own_fields(): void
    {
        // Receiver is $this — resolves to the Data itself, not a value object. Legitimate composition.
        $scopes = $this->scopes(<<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        final class ProfileData extends Data {
            public function __construct(public readonly string $first, public readonly string $last) {}
            public array $name { get => ['first' => $this->first, 'last' => $this->last]; }
        }
        PHP);

        $this->assertSame([], $scopes);
    }

    public function test_does_not_flag_a_composite_of_two_different_receivers(): void
    {
        $scopes = $this->scopes(<<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        final class Money { public function __construct(public int $cents) {} }
        final class OrderData extends Data {
            public function __construct(public readonly Money $price, public readonly Money $tax) {}
            public array $totals { get => ['price' => $this->price->cents, 'tax' => $this->tax->cents]; }
        }
        PHP);

        $this->assertSame([], $scopes);
    }

    public function test_does_not_flag_a_getter_returning_a_nested_data(): void
    {
        // Receiver resolves to a Data — just return/nest it; not a transformer case.
        $scopes = $this->scopes(<<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        final class LineData extends Data { public function __construct(public int $qty) {} }
        final class CartData extends Data {
            public function __construct(public readonly LineData $line) {}
            public array $summary { get => ['qty' => $this->line->qty, 'twice' => $this->line->qty]; }
        }
        PHP);

        $this->assertSame([], $scopes);
    }

    public function test_does_not_flag_when_a_literal_is_mixed_in(): void
    {
        $scopes = $this->scopes(<<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        final class Money { public function __construct(public int $cents, public string $code) {} }
        final class TagData extends Data {
            public function __construct(public readonly Money $money) {}
            public array $shape { get => ['amount' => $this->money->cents, 'currency' => 'EUR']; }
        }
        PHP);

        $this->assertSame([], $scopes);
    }
}
