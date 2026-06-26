<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\NearDuplicateFunctionDetector;
use PHPUnit\Framework\TestCase;

final class NearDuplicateFunctionDetectorTest extends TestCase
{
    public function test_flags_same_shape_methods_that_differ_only_in_names_and_literals(): void
    {
        $code = <<<'PHP'
        <?php
        class A {
            public function sumA(array $rows): int {
                $acc = 0;
                foreach ($rows as $r) {
                    if ($r > 0) { $acc += $r * 2; }
                }
                return $acc > 10 ? $acc : 0;
            }
            public function sumB(array $items): int {
                $total = 0;
                foreach ($items as $it) {
                    if ($it > 0) { $total += $it * 5; }
                }
                return $total > 20 ? $total : 0;
            }
            public function product(array $ns): string {
                return implode(',', array_map(static fn ($n) => (string) $n, $ns));
            }
        }
        PHP;

        $hits = (new NearDuplicateFunctionDetector)->find(Codebase::fromString($code));
        $scopes = array_map(static fn ($m): string => $m->scope(), $hits);
        sort($scopes);

        $this->assertSame(['A::sumA', 'A::sumB'], $scopes);
    }

    public function test_leaves_byte_identical_duplicates_to_the_exact_detector(): void
    {
        $code = <<<'PHP'
        <?php
        class A {
            public function run(int $x): int {
                $sum = 0;
                for ($i = 0; $i < $x; $i++) { $sum += $i * 2; }
                return $sum;
            }
        }
        class B {
            public function run(int $x): int {
                $sum = 0;
                for ($i = 0; $i < $x; $i++) { $sum += $i * 2; }
                return $sum;
            }
        }
        PHP;

        $this->assertSame([], (new NearDuplicateFunctionDetector)->find(Codebase::fromString($code)));
    }
}
