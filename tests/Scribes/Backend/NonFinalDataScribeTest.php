<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Scribes\Backend;

use JesseGall\CodeCommandments\Detectors\Backend\NonFinalDataDetector;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Scribes\Backend\NonFinalDataScribe;
use JesseGall\CodeCommandments\Scribes\RepentScribe;

final class NonFinalDataScribeTest extends ScribeTestCase
{
    protected function detector(): Detector
    {
        return new NonFinalDataDetector();
    }

    protected function scribe(): RepentScribe
    {
        return new NonFinalDataScribe();
    }

    public function test_seals_the_class_final_and_promotes_props_readonly(): void
    {
        $php = <<<'PHP'
        <?php

        namespace Spatie\LaravelData { class Data {} }

        namespace App {
            use Spatie\LaravelData\Data;

            class OrderData extends Data
            {
                public function __construct(
                    public string $id,
                    public int $total,
                ) {}
            }
        }
        PHP;

        $fixed = $this->fixStable($php);

        $this->assertStringContainsString('final class OrderData extends Data', $fixed);
        $this->assertStringContainsString('public readonly string $id', $fixed);
        $this->assertStringContainsString('public readonly int $total', $fixed);
    }

    public function test_does_not_overshoot_a_sealed_data_class_or_a_non_data_class(): void
    {
        // Already final+readonly → not flagged. A plain non-Data class → not flagged.
        $php = <<<'PHP'
        <?php

        namespace Spatie\LaravelData { class Data {} }

        namespace App {
            use Spatie\LaravelData\Data;

            final class TagData extends Data
            {
                public function __construct(
                    public readonly string $label,
                ) {}
            }

            class Service
            {
                public function __construct(
                    public string $name,
                ) {}
            }
        }
        PHP;

        $this->assertSame([], $this->findings($php));
        $this->assertSame($php, $this->fix($php));
    }

    public function test_adds_only_the_missing_readonly_when_some_props_already_have_it(): void
    {
        $php = <<<'PHP'
        <?php

        namespace Spatie\LaravelData { class Data {} }

        namespace App {
            use Spatie\LaravelData\Data;

            class MixedData extends Data
            {
                public function __construct(
                    public readonly string $id,
                    public int $count,
                ) {}
            }
        }
        PHP;

        $fixed = $this->fixStable($php);

        // The already-readonly prop keeps a single `readonly`, the other gains one.
        $this->assertStringContainsString('public readonly string $id', $fixed);
        $this->assertStringContainsString('public readonly int $count', $fixed);
        $this->assertStringNotContainsString('readonly readonly', $fixed);
    }
}
