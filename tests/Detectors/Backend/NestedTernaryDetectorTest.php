<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\NestedTernaryDetector;
use PHPUnit\Framework\TestCase;

final class NestedTernaryDetectorTest extends TestCase
{
    public function test_flags_the_outermost_node_of_a_chained_ternary_only(): void
    {
        $code = <<<'PHP'
        <?php
        class S {
            public function chained(int $n): string {
                return $n < 0 ? 'neg' : ($n === 0 ? 'zero' : 'pos');
            }
            public function plain(int $n): string {
                return $n < 0 ? 'neg' : 'pos';
            }
        }
        PHP;

        $hits = (new NestedTernaryDetector)->find(Codebase::fromString($code));

        $this->assertSame(['S::chained'], array_map(static fn ($m): string => $m->scope(), $hits));
    }
}
