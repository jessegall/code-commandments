<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests;

use JesseGall\CodeCommandments\Span;
use PHPUnit\Framework\TestCase;

/**
 * The line primitives every scribe measures with — {@see Span} owns the offset math, so the answers a
 * rewrite depends on are proven here rather than re-derived per scribe.
 */
final class SpanOffsetsTest extends TestCase
{
    public function test_a_token_beginning_its_line_is_told_from_one_trailing_code(): void
    {
        $source = "<?php\n    public string \$a; // note\n";

        $comment = strpos($source, '//');
        $declaration = strpos($source, 'public');

        $this->assertFalse(Span::startsItsLine($source, (int) $comment), 'a trailing note does not begin its line');
        $this->assertTrue(Span::startsItsLine($source, (int) $declaration), 'the declaration does');
    }

    public function test_a_token_at_column_zero_begins_its_line(): void
    {
        $source = "<?php\n/** Doc. */\nfunction f() {}\n";

        $doc = (int) strpos($source, '/**');

        $this->assertTrue(Span::startsItsLine($source, $doc));
        $this->assertSame('', Span::ownLineIndent($source, $doc), 'no indentation is still its own line');
    }

    public function test_line_content_end_stops_before_the_padding_and_the_break(): void
    {
        $source = "<?php\n    public string \$a; // note  \n    public int \$b;\n";

        $end = Span::lineContentEndAt($source, (int) strpos($source, 'public'));

        $this->assertSame('    public string $a; // note', substr($source, 6, $end - 6));
    }

    public function test_line_content_end_of_the_final_unterminated_line_is_its_end(): void
    {
        $source = "<?php\n\$a = 1;";

        $this->assertSame(strlen($source), Span::lineContentEndAt($source, strlen($source) - 1));
    }
}
