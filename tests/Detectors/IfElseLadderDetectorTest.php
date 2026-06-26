<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\IfElseLadderDetector;
use PHPUnit\Framework\TestCase;

final class IfElseLadderDetectorTest extends TestCase
{
    public function test_flags_an_if_with_two_or_more_elseifs_only(): void
    {
        $code = <<<'PHP'
        <?php
        class S {
            public function ladder($x): string {
                if ($x === 1) { return 'a'; }
                elseif ($x === 2) { return 'b'; }
                elseif ($x === 3) { return 'c'; }
                else { return 'z'; }
            }
            public function binary($x): string {
                if ($x === 1) { return 'a'; } else { return 'b'; }
            }
            public function oneElseif($x): string {
                if ($x === 1) { return 'a'; } elseif ($x === 2) { return 'b'; }
                return 'z';
            }
        }
        PHP;

        $hits = (new IfElseLadderDetector)->find(Codebase::fromString($code));

        $this->assertSame(['S::ladder'], array_map(static fn ($m): string => $m->scope(), $hits));
    }
}
