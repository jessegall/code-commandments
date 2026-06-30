<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Scribes\Backend;

use JesseGall\CodeCommandments\Detectors\Backend\RedundantElseDetector;
use JesseGall\CodeCommandments\Detectors\Detector;
use JesseGall\CodeCommandments\Scribes\Backend\RedundantElseScribe;
use JesseGall\CodeCommandments\Scribes\RepentScribe;

final class RedundantElseScribeTest extends ScribeTestCase
{
    protected function detector(): Detector
    {
        return new RedundantElseDetector();
    }

    protected function scribe(): RepentScribe
    {
        return new RedundantElseScribe();
    }

    public function test_drops_the_else_and_hoists_its_body_after_the_guard(): void
    {
        $php = <<<'PHP'
        <?php

        class Greeter
        {
            public function greet(bool $known): string
            {
                if ($known) {
                    return 'hi';
                } else {
                    $msg = 'hello stranger';

                    return $msg;
                }
            }
        }
        PHP;

        $fixed = $this->fixStable($php);

        // The guard is kept verbatim…
        $this->assertStringContainsString("if (\$known) {\n            return 'hi';\n        }", $fixed);
        // …the else wrapper is gone…
        $this->assertStringNotContainsString('else', $fixed);
        // …and its body is hoisted to the guard's level (dedented one step).
        $this->assertStringContainsString("        \$msg = 'hello stranger';", $fixed);
        $this->assertStringContainsString("        return \$msg;", $fixed);
    }

    public function test_does_not_overshoot_a_genuine_else(): void
    {
        // The first if/else is redundant (its if-branch returns); the second is a
        // genuine either/or (neither branch exits) and must be left byte-identical.
        $php = <<<'PHP'
        <?php

        class Router
        {
            public function pick(bool $a): string
            {
                if ($a) {
                    return 'left';
                } else {
                    return 'right';
                }
            }

            public function classify(int $n): string
            {
                $label = '';

                if ($n > 0) {
                    $label = 'positive';
                } else {
                    $label = 'non-positive';
                }

                return $label;
            }
        }
        PHP;

        $fixed = $this->fix($php);

        // The genuine either/or survives untouched…
        $this->assertStringContainsString("if (\$n > 0) {\n            \$label = 'positive';\n        } else {\n            \$label = 'non-positive';\n        }", $fixed);
        // …while the redundant one is unwrapped.
        $this->assertStringContainsString("            return 'left';\n        }\n\n        return 'right';", $fixed);
        $this->assertParses($fixed);
    }
}
