<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Scribes\Backend;

use JesseGall\CodeCommandments\Detectors\Backend\NestedTernaryDetector;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Scribes\Backend\NestedTernaryScribe;
use JesseGall\CodeCommandments\Scribes\RepentScribe;

final class NestedTernaryScribeTest extends ScribeTestCase
{
    protected function detector(): Detector
    {
        return new NestedTernaryDetector();
    }

    protected function scribe(): RepentScribe
    {
        return new NestedTernaryScribe();
    }

    public function test_unfolds_an_else_chained_ternary_into_match_true(): void
    {
        $php = <<<'PHP'
        <?php

        class Grader
        {
            public function grade(int $n): string
            {
                return $n > 90 ? 'A' : ($n > 80 ? 'B' : 'C');
            }
        }
        PHP;

        $fixed = $this->fixStable($php);

        $expected = <<<'TXT'
                return match (true) {
                    $n > 90 => 'A',
                    $n > 80 => 'B',
                    default => 'C',
                };
        TXT;

        $this->assertStringContainsString($expected, $fixed);
    }

    public function test_does_not_overshoot_a_single_ternary_or_a_then_nested_chain(): void
    {
        // A plain single ternary is not flagged at all; a THEN-nested chain IS flagged by
        // the detector but the scribe skips it (it can't flatten to a clean match), so the
        // source is left unchanged.
        $php = <<<'PHP'
        <?php

        class Picker
        {
            public function simple(bool $a, string $b, string $c): string
            {
                return $a ? $b : $c;
            }

            public function thenNested(bool $a, bool $b): string
            {
                return $a ? ($b ? 'x' : 'y') : 'z';
            }
        }
        PHP;

        $this->assertSame($php, $this->fix($php));
        $this->assertStringContainsString("return \$a ? (\$b ? 'x' : 'y') : 'z';", $this->fix($php));
    }
}
