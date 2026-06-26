<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\DeepNestingDetector;
use PHPUnit\Framework\TestCase;

final class DeepNestingDetectorTest extends TestCase
{
    public function test_flags_a_three_deep_if_pyramid_only(): void
    {
        $code = <<<'PHP'
        <?php
        class S {
            public function pyramid($a, $b, $c): string {
                if ($a) {
                    if ($b) {
                        if ($c) {
                            return 'deep';
                        }
                    }
                }
                return 'shallow';
            }
            public function twoDeep($a, $b): string {
                if ($a) {
                    if ($b) {
                        return 'ok';
                    }
                }
                return 'no';
            }
        }
        PHP;

        $hits = (new DeepNestingDetector)->find(Codebase::fromString($code));

        // only the innermost `if` of the 3-deep pyramid; two-deep is fine.
        $this->assertSame(['S::pyramid'], array_map(static fn ($m): string => $m->scope(), $hits));
    }
}
