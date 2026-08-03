<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Scribes\Backend;

use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Backend\ShortCircuitStatementDetector;
use JesseGall\CodeCommandments\Scribes\Backend\ShortCircuitStatementScribe;
use JesseGall\CodeCommandments\Scribes\RepentScribe;

final class ShortCircuitStatementScribeTest extends ScribeTestCase
{
    protected function detector(): Detector
    {
        return new ShortCircuitStatementDetector();
    }

    protected function scribe(): RepentScribe
    {
        return new ShortCircuitStatementScribe();
    }

    public function test_unfolds_an_and_into_an_if(): void
    {
        $php = <<<'PHP'
        <?php

        class Builder
        {
            public function rebuild(Node $node): void
            {
                $node->isBuilt() && $node->built()->forgetComposition();
            }
        }
        PHP;

        $fixed = $this->fixStable($php);

        $this->assertStringContainsString(
            "        if (\$node->isBuilt()) {\n            \$node->built()->forgetComposition();\n        }",
            $fixed,
        );
    }

    public function test_flips_an_or_because_it_runs_when_the_left_does_not_hold(): void
    {
        $php = <<<'PHP'
        <?php

        class Builder
        {
            public function rebuild(Node $node): void
            {
                $node->isBuilt() || $node->build();
            }
        }
        PHP;

        $fixed = $this->fixStable($php);

        $this->assertStringContainsString(
            "        if (! \$node->isBuilt()) {\n            \$node->build();\n        }",
            $fixed,
        );
    }

    public function test_keeps_a_whole_chain_as_the_condition(): void
    {
        $php = <<<'PHP'
        <?php

        class Watch
        {
            public function tick(int $idle, bool $unlocked): void
            {
                $unlocked && $idle > 300 && $this->sleep();
            }
        }
        PHP;

        $fixed = $this->fixStable($php);

        $this->assertStringContainsString(
            "        if (\$unlocked && \$idle > 300) {\n            \$this->sleep();\n        }",
            $fixed,
        );
    }

    public function test_flips_an_equality_at_the_operator(): void
    {
        // An equality has an exact inverse, so the flipped condition reads as a human writes it.
        $php = <<<'PHP'
        <?php

        class Totals
        {
            public function add(array $entry): void
            {
                $entry['cents'] === 0 || $this->record($entry);
            }
        }
        PHP;

        $fixed = $this->fixStable($php);

        $this->assertStringContainsString("if (\$entry['cents'] !== 0) {", $fixed);
    }

    public function test_opens_the_block_in_the_files_own_brace_style(): void
    {
        $php = <<<'PHP'
        <?php

        class Sockets
        {
            public function sort(array $sockets): void
            {
                foreach ($sockets as $socket)
                {
                    $socket->isInput() && $this->keep($socket);
                }
            }
        }
        PHP;

        $fixed = $this->fixStable($php);

        $this->assertStringContainsString(
            "            if (\$socket->isInput())\n            {\n                \$this->keep(\$socket);\n            }",
            $fixed,
        );
    }

    public function test_leaves_a_short_circuit_whose_result_is_read(): void
    {
        $php = <<<'PHP'
        <?php

        class Builder
        {
            public function ready(Node $node): bool
            {
                return $node->isBuilt() && $node->isFresh();
            }
        }
        PHP;

        $this->assertFalse($this->rewrote($php));
    }
}
