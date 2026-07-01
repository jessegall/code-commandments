<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Scribes\Backend;

use JesseGall\CodeCommandments\Detectors\Backend\LoopInvertedGuardDetector;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Scribes\Backend\LoopInvertedGuardScribe;
use JesseGall\CodeCommandments\Scribes\RepentScribe;

final class LoopInvertedGuardScribeTest extends ScribeTestCase
{
    protected function detector(): Detector
    {
        return new LoopInvertedGuardDetector();
    }

    protected function scribe(): RepentScribe
    {
        return new LoopInvertedGuardScribe();
    }

    public function test_inverts_the_sole_body_if_into_a_continue_guard(): void
    {
        $php = <<<'PHP'
        <?php

        class Processor
        {
            public function run(array $rows): void
            {
                foreach ($rows as $row) {
                    if ($row->valid()) {
                        $this->store($row);
                        $this->log($row);
                    }
                }
            }
        }
        PHP;

        $fixed = $this->fixStable($php);

        // Inverted condition + continue guard (at the loop-body level, indent 12)…
        $this->assertStringContainsString("if (! (\$row->valid())) {\n                continue;\n            }", $fixed);
        // …body hoisted to the loop level (dedented one step), order preserved.
        $this->assertStringContainsString("            \$this->store(\$row);\n            \$this->log(\$row);", $fixed);
    }

    public function test_does_not_overshoot_a_single_statement_filter_or_a_plain_if(): void
    {
        // First loop: a ONE-statement body if (a filter-collect) — not flagged.
        // Second: a plain method-level if (not in a loop) — not flagged.
        $php = <<<'PHP'
        <?php

        class Keeper
        {
            public function collect(array $rows): array
            {
                $out = [];

                foreach ($rows as $row) {
                    if ($row->keep()) {
                        $out[] = $row;
                    }
                }

                return $out;
            }

            public function label(bool $on): string
            {
                if ($on) {
                    $a = 'x';

                    return $a;
                }

                return 'off';
            }
        }
        PHP;

        $this->assertSame([], $this->findings($php));
        $this->assertSame($php, $this->fix($php));
    }
}
