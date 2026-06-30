<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\NewDataObjectDetector;
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
                public function build(LineData $line): OrderData { return new OrderData($line); }
            }
        }
        PHP;

        $hits = (new NewDataObjectDetector)->find(Codebase::fromString($code));

        $this->assertSame(['App\\Builder::build'], array_map(static fn ($m): string => $m->scope(), $hits));
    }
}
