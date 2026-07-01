<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Scribes\Backend;

use JesseGall\CodeCommandments\Detectors\Backend\Spatie\NewDataObjectDetector;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Scribes\Backend\NewDataObjectScribe;
use JesseGall\CodeCommandments\Scribes\RepentScribe;

final class NewDataObjectScribeTest extends ScribeTestCase
{
    protected function detector(): Detector
    {
        return new NewDataObjectDetector();
    }

    protected function scribe(): RepentScribe
    {
        return new NewDataObjectScribe();
    }

    /** A rich (nested-Data prop) class, plus a plain one, with the Spatie stubs. */
    private const DATA = <<<'PHP'
        namespace Spatie\LaravelData {
            class Data {}
        }

        namespace Spatie\LaravelData\Attributes {
            #[\Attribute] class MapInputName { public function __construct($m = null) {} }
        }

        namespace App {
            use Spatie\LaravelData\Data;

            final class MoneyData extends Data
            {
                public function __construct(public readonly int $cents) {}
            }

            final class OrderData extends Data
            {
                public function __construct(
                    public readonly string $id,
                    public readonly MoneyData $total,
                ) {}
            }
        PHP;

    public function test_rewrites_named_arguments_into_a_from_call(): void
    {
        $php = "<?php\n\n" . self::DATA . <<<'PHP'

            final class Maker
            {
                public function make(MoneyData $total): OrderData
                {
                    return new OrderData(id: 'abc', total: $total);
                }
            }
        }
        PHP;

        $fixed = $this->fixStable($php);

        $this->assertStringContainsString("return OrderData::from(['id' => 'abc', 'total' => \$total]);", $fixed);
        $this->assertStringNotContainsString('new OrderData', $fixed);
    }

    public function test_resolves_positional_arguments_to_property_names(): void
    {
        $php = "<?php\n\n" . self::DATA . <<<'PHP'

            final class Maker
            {
                public function make(MoneyData $total): OrderData
                {
                    return new OrderData('abc', $total);
                }
            }
        }
        PHP;

        $fixed = $this->fixStable($php);

        // The positional args are mapped to property names via the constructor.
        $this->assertStringContainsString("return OrderData::from(['id' => 'abc', 'total' => \$total]);", $fixed);
        $this->assertStringNotContainsString('new OrderData', $fixed);
    }

    public function test_skips_a_class_that_remaps_input_names(): void
    {
        // OrderData uses #[MapInputName] on a prop → `::from()` keys by the mapped name, so a
        // property-name-keyed rewrite would mismap. The detector still flags it (it's rich),
        // but the scribe must leave it untouched.
        $php = <<<'PHP'
        <?php

        namespace Spatie\LaravelData { class Data {} }
        namespace Spatie\LaravelData\Attributes {
            #[\Attribute] class MapInputName { public function __construct($m = null) {} }
        }

        namespace App {
            use Spatie\LaravelData\Data;
            use Spatie\LaravelData\Attributes\MapInputName;

            final class MoneyData extends Data
            {
                public function __construct(public readonly int $cents) {}
            }

            final class MappedOrderData extends Data
            {
                public function __construct(
                    #[MapInputName('order_id')]
                    public readonly string $id,
                    public readonly MoneyData $total,
                ) {}
            }

            final class Maker
            {
                public function make(MoneyData $total): MappedOrderData
                {
                    return new MappedOrderData(id: 'abc', total: $total);
                }
            }
        }
        PHP;

        // It IS flagged (rich), but NOT rewritten.
        $this->assertNotSame([], $this->findings($php));
        $this->assertSame($php, $this->fix($php));
    }

    public function test_does_not_overshoot_a_plain_data_class(): void
    {
        $php = "<?php\n\n" . self::DATA . <<<'PHP'

            final class Maker
            {
                public function plain(): MoneyData
                {
                    return new MoneyData(cents: 100);
                }
            }
        }
        PHP;

        // MoneyData is plain (scalar prop only) → never flagged, never rewritten.
        $this->assertSame([], $this->findings($php));
        $this->assertSame($php, $this->fix($php));
    }
}
