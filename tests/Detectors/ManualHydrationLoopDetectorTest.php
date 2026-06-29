<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\ManualHydrationLoopDetector;
use PHPUnit\Framework\TestCase;

final class ManualHydrationLoopDetectorTest extends TestCase
{
    public function test_flags_data_from_inside_a_loop_only(): void
    {
        $code = <<<'PHP'
        <?php
        namespace Spatie\LaravelData { class Data {} }
        namespace App {
            use Spatie\LaravelData\Data;
            class LineData extends Data {}
            class Plain {}
            class Mapper {
                public function loop(array $rows): array {
                    $out = [];
                    foreach ($rows as $row) {
                        $out[] = LineData::from($row);
                    }
                    return $out;
                }
                public function single(array $row): LineData {
                    return LineData::from($row);
                }
                public function other(array $rows): array {
                    $out = [];
                    foreach ($rows as $row) {
                        $out[] = Plain::from($row);
                    }
                    return $out;
                }
            }
        }
        PHP;

        $hits = (new ManualHydrationLoopDetector)->find(Codebase::fromString($code));

        $this->assertSame(['App\\Mapper::loop'], array_map(static fn ($m): string => $m->scope(), $hits));
    }
}
