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
        $this->assertStringContainsString("if (! \$row->valid()) {\n                continue;\n            }", $fixed);
        // …body hoisted to the loop level (dedented one step), order preserved.
        $this->assertStringContainsString("            \$this->store(\$row);\n            \$this->log(\$row);", $fixed);
    }

    public function test_flips_an_equality_at_the_operator(): void
    {
        // An equality has an exact inverse, so it takes one — not a `!` and a pair of parentheses.
        $php = <<<'PHP'
        <?php

        class Processor
        {
            public function run(array $rows): void
            {
                foreach ($rows as $row) {
                    if ($row->state === 'ready') {
                        $this->store($row);
                        $this->log($row);
                    }
                }
            }
        }
        PHP;

        $this->assertStringContainsString("if (\$row->state !== 'ready') {", $this->fixStable($php));
    }

    public function test_keeps_the_parentheses_a_relational_comparison_actually_needs(): void
    {
        // `<` and `>=` are BOTH false for a NAN operand, so they are not inverses: this one keeps
        // the faithful `!`, which `!` binding tighter than `<` means it cannot lose its parentheses.
        $php = <<<'PHP'
        <?php

        class Processor
        {
            public function run(array $rows): void
            {
                foreach ($rows as $row) {
                    if ($row->weight < 250.0) {
                        $this->store($row);
                        $this->log($row);
                    }
                }
            }
        }
        PHP;

        $this->assertStringContainsString("if (! (\$row->weight < 250.0)) {", $this->fixStable($php));
    }

    public function test_writes_the_guard_in_the_files_own_brace_style(): void
    {
        // #416: the fix arrived K&R in an Allman codebase and had to be reformatted by hand.
        $php = <<<'PHP'
        <?php

        class Processor
        {
            public function run(array $rows): void
            {
                foreach ($rows as $row)
                {
                    if ($row->valid())
                    {
                        $this->store($row);
                        $this->log($row);
                    }
                }
            }
        }
        PHP;

        $expected = <<<'PHP'
        <?php

        class Processor
        {
            public function run(array $rows): void
            {
                foreach ($rows as $row)
                {
                    if (! $row->valid())
                    {
                        continue;
                    }

                    $this->store($row);
                    $this->log($row);
                }
            }
        }
        PHP;

        $this->assertSame($expected, $this->fixStable($php));
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
