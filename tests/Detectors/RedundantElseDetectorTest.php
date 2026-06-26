<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\RedundantElseDetector;
use PHPUnit\Framework\TestCase;

final class RedundantElseDetectorTest extends TestCase
{
    public function test_flags_else_after_an_exiting_if_only(): void
    {
        $code = <<<'PHP'
        <?php
        class S {
            public function returns($x): string {
                if ($x) { return 'a'; } else { return 'b'; }
            }
            public function throws($x): string {
                if (! $x) { throw new \RuntimeException('no'); } else { return 'ok'; }
            }
            public function noExit($x): string {
                $r = '';
                if ($x) { $r = 'a'; } else { $r = 'b'; }
                return $r;
            }
            public function guarded($x): string {
                if (! $x) { return 'b'; }
                return 'a';
            }
        }
        PHP;

        $hits = (new RedundantElseDetector)->find(Codebase::fromString($code));
        $scopes = array_map(static fn ($m): string => $m->scope(), $hits);
        sort($scopes);

        // returns + throws have an exiting if-branch; noExit doesn't; guarded has no else.
        $this->assertSame(['S::returns', 'S::throws'], $scopes);
    }
}
