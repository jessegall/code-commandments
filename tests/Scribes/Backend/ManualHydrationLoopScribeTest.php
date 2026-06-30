<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Scribes\Backend;

use JesseGall\CodeCommandments\Detectors\Backend\ManualHydrationLoopDetector;
use JesseGall\CodeCommandments\Detectors\Detector;
use JesseGall\CodeCommandments\Scribes\Backend\ManualHydrationLoopScribe;
use JesseGall\CodeCommandments\Scribes\RepentScribe;

final class ManualHydrationLoopScribeTest extends ScribeTestCase
{
    protected function detector(): Detector
    {
        return new ManualHydrationLoopDetector();
    }

    protected function scribe(): RepentScribe
    {
        return new ManualHydrationLoopScribe();
    }

    private const DATA = <<<'PHP'
        namespace Spatie\LaravelData { class Data {} }

        namespace App {
            use Spatie\LaravelData\Data;

            final class LineData extends Data
            {
                public function __construct(public readonly string $value) {}
            }
        PHP;

    public function test_rewrites_an_array_map_arrow_fn_into_collect(): void
    {
        $php = "<?php\n\n" . self::DATA . <<<'PHP'

            final class Mapper
            {
                public function map(array $rows): array
                {
                    return array_map(fn ($r) => LineData::from($r), $rows);
                }
            }
        }
        PHP;

        $fixed = $this->fixStable($php);

        $this->assertStringContainsString('return LineData::collect($rows);', $fixed);
        $this->assertStringNotContainsString('array_map', $fixed);
    }

    public function test_rewrites_an_array_map_first_class_callable_into_collect(): void
    {
        $php = "<?php\n\n" . self::DATA . <<<'PHP'

            final class Mapper
            {
                public function map(array $rows): array
                {
                    return array_map(LineData::from(...), $rows);
                }
            }
        }
        PHP;

        $fixed = $this->fixStable($php);

        $this->assertStringContainsString('return LineData::collect($rows);', $fixed);
        $this->assertStringNotContainsString('array_map', $fixed);
    }

    public function test_does_not_overshoot_a_transforming_callback_or_a_foreach(): void
    {
        // The arrow fn transforms the item before from() ($r['data'] != $r), so collect()
        // is NOT equivalent → skipped. The foreach accumulator needs surrounding context →
        // skipped. Both are still flagged by the detector; just not auto-fixed.
        $php = "<?php\n\n" . self::DATA . <<<'PHP'

            final class Mapper
            {
                public function transforming(array $rows): array
                {
                    return array_map(fn ($r) => LineData::from($r['data']), $rows);
                }

                public function looped(array $rows): array
                {
                    $out = [];

                    foreach ($rows as $r) {
                        $out[] = LineData::from($r);
                    }

                    return $out;
                }
            }
        }
        PHP;

        // Both sites are flagged…
        $this->assertNotSame([], $this->findings($php));
        // …but neither is rewritten.
        $this->assertSame($php, $this->fix($php));
    }
}
