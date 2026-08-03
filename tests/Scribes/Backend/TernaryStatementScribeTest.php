<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Scribes\Backend;

use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Backend\TernaryStatementDetector;
use JesseGall\CodeCommandments\Scribes\Backend\TernaryStatementScribe;
use JesseGall\CodeCommandments\Scribes\RepentScribe;

final class TernaryStatementScribeTest extends ScribeTestCase
{
    protected function detector(): Detector
    {
        return new TernaryStatementDetector();
    }

    protected function scribe(): RepentScribe
    {
        return new TernaryStatementScribe();
    }

    public function test_unfolds_a_statement_ternary_into_an_if_else(): void
    {
        $php = <<<'PHP'
        <?php

        class Tree
        {
            public function collect(array $children): array
            {
                $gone = [];

                foreach ($children as $child) {
                    $this->holds($child->id)
                        ? array_push($gone, ...$this->collect($child->kids))
                        : $gone[] = $child->id;
                }

                return $gone;
            }
        }
        PHP;

        $fixed = $this->fixStable($php);

        $this->assertStringContainsString(
            "            if (\$this->holds(\$child->id)) {\n"
            . "                array_push(\$gone, ...\$this->collect(\$child->kids));\n"
            . "            } else {\n"
            . "                \$gone[] = \$child->id;\n"
            . "            }",
            $fixed,
        );
    }

    public function test_a_short_ternary_has_no_then_branch_to_re_evaluate(): void
    {
        $php = <<<'PHP'
        <?php

        class Tree
        {
            public function warn(?string $name): void
            {
                $name ?: $this->complain();
            }
        }
        PHP;

        $fixed = $this->fixStable($php);

        $this->assertStringContainsString("        if (! \$name) {\n            \$this->complain();\n        }", $fixed);
        $this->assertStringNotContainsString('else', $fixed);
    }

    public function test_stands_the_else_on_its_own_line_when_the_file_does(): void
    {
        $php = <<<'PHP'
        <?php

        class Tree
        {
            public function collect(array $children, array $gone): array
            {
                foreach ($children as $child)
                {
                    $this->holds($child->id) ? $this->keep($child) : $gone[] = $child->id;
                }

                return $gone;
            }
        }
        PHP;

        $fixed = $this->fixStable($php);

        $this->assertStringContainsString(
            "            if (\$this->holds(\$child->id))\n"
            . "            {\n"
            . "                \$this->keep(\$child);\n"
            . "            }\n"
            . "            else\n"
            . "            {\n"
            . "                \$gone[] = \$child->id;\n"
            . "            }",
            $fixed,
        );
    }

    public function test_leaves_a_ternary_whose_value_is_read(): void
    {
        $php = <<<'PHP'
        <?php

        class Tree
        {
            public function label(array $row): string
            {
                return $row['name'] ? $row['name'] : 'anonymous';
            }
        }
        PHP;

        $this->assertFalse($this->rewrote($php));
    }
}
