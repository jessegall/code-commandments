<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\DuplicateFunctionDetector;
use PHPUnit\Framework\TestCase;

final class DuplicateFunctionDetectorTest extends TestCase
{
    public function test_flags_identical_functions_ignoring_formatting_and_comments(): void
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
            public function run(int $x): int
            {
                // a copy with different whitespace and a comment
                $sum = 0;

                for ($i = 0; $i < $x; $i++) {
                    $sum += $i * 2;
                }

                return $sum;
            }
        }
        class C {
            public function different(int $x): int {
                $sum = 1;
                for ($i = 0; $i < $x; $i++) { $sum *= $i + 3; }
                return $sum;
            }
        }
        PHP;

        $hits = (new DuplicateFunctionDetector)->find(Codebase::fromString($code));
        $scopes = array_map(static fn ($m): string => $m->scope(), $hits);
        sort($scopes);

        $this->assertSame(['A::run', 'B::run'], $scopes);
    }

    public function test_ignores_trivial_methods_below_the_size_floor(): void
    {
        $code = <<<'PHP'
        <?php
        class A { public function id(): string { return $this->id; } }
        class B { public function id(): string { return $this->id; } }
        PHP;

        $this->assertSame([], (new DuplicateFunctionDetector)->find(Codebase::fromString($code)));
    }
}
