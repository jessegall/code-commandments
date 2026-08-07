<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\AssembledTemplateDetector;
use PHPUnit\Framework\TestCase;

final class AssembledTemplateDetectorTest extends TestCase
{
    public function test_flags_a_template_assembled_as_lines_and_joined(): void
    {
        // The shape this exists for: the OUTPUT is unreadable. `/**` and ` */` are separate
        // elements so the docblock is not visibly a block, the indentation is spaces inside a
        // quote, and the `implode` that makes them LINES is somewhere else entirely.
        $codebase = Codebase::fromString(<<<'PHP'
            <?php
            final class Scaffold
            {
                public function render(string $name): string
                {
                    $lines = [
                        '/**',
                        " * Generated for {$name}.",
                        ' */',
                        'final class Thing',
                        '{',
                        '}',
                    ];

                    return implode("\n", $lines);
                }
            }
            PHP);

        $this->assertSame(['Scaffold::render'], $this->scopes($codebase));
    }

    public function test_flags_it_joined_inline_too(): void
    {
        $codebase = Codebase::fromString(<<<'PHP'
            <?php
            final class Inline
            {
                public function head(): string
                {
                    return implode("\n", [
                        '<?php',
                        '',
                        'declare(strict_types=1);',
                    ]);
                }
            }
            PHP);

        $this->assertSame(['Inline::head'], $this->scopes($codebase));
    }

    public function test_flags_a_php_eol_join(): void
    {
        $codebase = Codebase::fromString(<<<'PHP'
            <?php
            final class Eol
            {
                public function block(): string
                {
                    $rows = ['name: a', 'kind: b', 'note: c'];

                    return implode(PHP_EOL, $rows);
                }
            }
            PHP);

        $this->assertSame(['Eol::block'], $this->scopes($codebase));
    }

    public function test_leaves_a_joined_list_of_computed_values_alone(): void
    {
        // Not a template: the parts are VALUES the program computed, and the join is how they are
        // presented. There is no fixed shape a heredoc could state.
        $codebase = Codebase::fromString(<<<'PHP'
            <?php
            final class Report
            {
                public function render(array $findings): string
                {
                    $lines = array_map(static fn (object $f): string => $f->label, $findings);

                    return implode("\n", $lines);
                }
            }
            PHP);

        $this->assertSame([], $this->scopes($codebase));
    }

    public function test_leaves_a_non_newline_join_alone(): void
    {
        // Joining with `, ` builds ONE line out of parts — that is not a template, and a heredoc
        // would say nothing about it.
        $codebase = Codebase::fromString(<<<'PHP'
            <?php
            final class Csv
            {
                public function head(): string
                {
                    return implode(', ', ['id', 'name', 'total']);
                }
            }
            PHP);

        $this->assertSame([], $this->scopes($codebase));
    }

    public function test_leaves_a_short_join_alone(): void
    {
        // Two lines is a pair, not a template — the shape is already visible.
        $codebase = Codebase::fromString(<<<'PHP'
            <?php
            final class Pair
            {
                public function note(string $why): string
                {
                    return implode("\n", ['Blocked:', $why]);
                }
            }
            PHP);

        $this->assertSame([], $this->scopes($codebase));
    }

    /**
     * @return list<string>
     */
    private function scopes(Codebase $codebase): array
    {
        return array_map(
            static fn (object $match): string => $match->scope(),
            new AssembledTemplateDetector()->find($codebase),
        );
    }
}
