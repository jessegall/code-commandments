<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Text;
use PHPUnit\Framework\TestCase;

/**
 * Laying words out for a person. A terminal prints a four-hundred-character paragraph as one line and lets
 * the window fold it wherever it likes, and a message arrives already wrapped to whatever width it was
 * written at — so both have to be undone before anything is readable.
 */
final class TextTest extends TestCase
{
    private string|false $priorColumns;

    protected function setUp(): void
    {
        $this->priorColumns = getenv('COLUMNS');
        putenv('COLUMNS=40');
    }

    protected function tearDown(): void
    {
        putenv($this->priorColumns === false ? 'COLUMNS' : 'COLUMNS=' . $this->priorColumns);
    }

    /**
     * Every line after the first is indented, whether the wrap made it or the text already had it — the
     * caller has put a label in front of the first, and the rest must sit under the words.
     */
    public function test_it_indents_every_line_after_the_first(): void
    {
        $wrapped = Text::wrap("first line here\nsecond line here", 6);

        $this->assertStringStartsWith('first line here', $wrapped);
        $this->assertStringContainsString("\n      second line here", $wrapped);
    }

    /**
     * Re-wrapping text that was already hard-wrapped strands two words on every other line, so prose is
     * joined back into a paragraph before it is wrapped again.
     */
    public function test_it_reflows_a_paragraph_that_was_already_wrapped(): void
    {
        $reflowed = Text::reflow("the quick brown fox\njumps over the lazy dog", 0);

        $this->assertStringContainsString('the quick brown fox jumps', $reflowed);
    }

    /**
     * A table, a list or an indented block means the breaks were deliberate, and moving them destroys it.
     */
    public function test_it_leaves_a_shape_that_was_meant(): void
    {
        foreach (["| a | b |\n|---|---|", "- one\n- two", "  indented\n  block"] as $shaped) {
            $this->assertSame($shaped, Text::reflow($shaped, 0), 'a deliberate shape must survive');
        }
    }

    public function test_a_blank_line_stays_a_blank_line(): void
    {
        $this->assertStringContainsString("\n\n", Text::reflow("one paragraph\n\nanother paragraph", 0));
    }

    /**
     * A word longer than the line is not broken — a path or an id must stay selectable.
     */
    public function test_a_long_word_is_not_cut(): void
    {
        $path = str_repeat('a', 60) . '/b.php';

        $this->assertStringContainsString($path, Text::wrap($path, 4));
    }
}
