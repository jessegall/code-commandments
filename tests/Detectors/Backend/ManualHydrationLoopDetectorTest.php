<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\Spatie\ManualHydrationLoopDetector;
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

    public function test_does_not_flag_a_tolerant_decode_loop_that_skips_bad_entries(): void
    {
        $code = <<<'PHP'
        <?php
        namespace Spatie\LaravelData { class Data {} }
        namespace App {
            use Spatie\LaravelData\Data;
            class LineData extends Data {}
            class Reader {
                // try/catch + continue inside the loop = tolerant decoder; ::collect() can't skip.
                public function tolerant(array $rows): array {
                    $out = [];
                    foreach ($rows as $row) {
                        try {
                            $out[] = LineData::from($row);
                        } catch (\Throwable $e) {
                            continue;
                        }
                    }
                    return $out;
                }
            }
        }
        PHP;

        $hits = (new ManualHydrationLoopDetector)->find(Codebase::fromString($code));

        $this->assertSame([], array_map(static fn ($m): string => $m->scope(), $hits));
    }

    public function test_does_not_flag_a_keyed_map_build(): void
    {
        $code = <<<'PHP'
        <?php
        namespace Spatie\LaravelData { class Data {} }
        namespace App {
            use Spatie\LaravelData\Data;
            class ModelData extends Data {}
            class Catalogue {
                // keyed by a computed id + merged into each item — ::collect() returns a LIST.
                public function keyed(array $entries): array {
                    $catalog = [];
                    foreach ($entries as $id => $entry) {
                        $catalog[$id] = ModelData::from([...$entry, 'id' => $id]);
                    }
                    return $catalog;
                }
            }
        }
        PHP;

        $hits = (new ManualHydrationLoopDetector)->find(Codebase::fromString($code));

        $this->assertSame([], array_map(static fn ($m): string => $m->scope(), $hits));
    }

    public function test_still_flags_a_method_level_try_catch_around_the_whole_map(): void
    {
        // A try/catch OUTSIDE the loop (one failure aborts all) is not tolerant — still a sin.
        $code = <<<'PHP'
        <?php
        namespace Spatie\LaravelData { class Data {} }
        namespace App {
            use Spatie\LaravelData\Data;
            class LineData extends Data {}
            class Wrapper {
                public function wrapped(array $rows): array {
                    $out = [];
                    try {
                        foreach ($rows as $row) {
                            $out[] = LineData::from($row);
                        }
                    } catch (\Throwable $e) {
                        return [];
                    }
                    return $out;
                }
            }
        }
        PHP;

        $hits = (new ManualHydrationLoopDetector)->find(Codebase::fromString($code));

        $this->assertSame(['App\\Wrapper::wrapped'], array_map(static fn ($m): string => $m->scope(), $hits));
    }
}
