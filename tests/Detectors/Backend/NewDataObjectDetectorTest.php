<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\Spatie\NewDataObjectDetector;
use PHPUnit\Framework\TestCase;

final class NewDataObjectDetectorTest extends TestCase
{
    public function test_flags_new_on_a_rich_data_class_but_not_a_plain_one(): void
    {
        $code = <<<'PHP'
        <?php
        namespace Spatie\LaravelData { class Data {} }
        namespace Spatie\LaravelData\Attributes { class WithCast { public function __construct($c) {} } }
        namespace App {
            use Spatie\LaravelData\Data;
            use Spatie\LaravelData\Attributes\WithCast;

            // RICH: a cast `::from()` runs and `new` skips
            final class MoneyData extends Data {
                public function __construct(#[WithCast('cast')] public readonly int $cents) {}
            }
            // PLAIN: scalars only — `::from()` and `new` are equivalent
            final class TagData extends Data {
                public function __construct(public readonly string $id, public readonly string $label) {}
            }

            final class Factory {
                public function rich(): MoneyData { return new MoneyData(100); }
                public function plain(): TagData { return new TagData('a', 'b'); }
            }
        }
        PHP;

        $hits = (new NewDataObjectDetector)->find(Codebase::fromString($code));

        $this->assertSame(['App\\Factory::rich'], array_map(static fn ($m): string => $m->scope(), $hits));
    }

    public function test_flags_new_on_a_nested_data_class(): void
    {
        $code = <<<'PHP'
        <?php
        namespace Spatie\LaravelData { class Data {} }
        namespace App {
            use Spatie\LaravelData\Data;

            final class LineData extends Data {
                public function __construct(public readonly string $sku) {}
            }
            // RICH by nesting: a promoted prop is itself a Data class
            final class OrderData extends Data {
                public function __construct(public readonly LineData $line) {}
            }

            final class Builder {
                // Raw input in the nested slot — `::from()` is the decode `new` skips.
                public function build(array $line): OrderData { return new OrderData($line); }
            }
        }
        PHP;

        $hits = (new NewDataObjectDetector)->find(Codebase::fromString($code));

        $this->assertSame(['App\\Builder::build'], array_map(static fn ($m): string => $m->scope(), $hits));
    }

    public function test_does_not_flag_a_new_handed_the_constructed_nested_data(): void
    {
        // #481: `::from()` is a boundary DECODE. Handed a `LineData` the caller already built, it
        // re-decodes our own value and loses whatever the shape cannot carry — `new` is the honest
        // construction, so the sin does not stand. Positional or named, the answer is the same.
        $code = <<<'PHP'
        <?php
        namespace Spatie\LaravelData { class Data {} }
        namespace App {
            use Spatie\LaravelData\Data;

            final class LineData extends Data {
                public function __construct(public readonly string $sku) {}
            }
            final class OrderData extends Data {
                public function __construct(public readonly string $id, public readonly LineData $line) {}
            }

            final class Builder {
                public function positional(LineData $line): OrderData { return new OrderData('a', $line); }
                public function named(LineData $line): OrderData { return new OrderData(id: 'a', line: $line); }
            }
        }
        PHP;

        $this->assertSame([], (new NewDataObjectDetector)->find(Codebase::fromString($code)));
    }
}
