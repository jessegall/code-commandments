<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ast;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Scribes\Span;
use PHPUnit\Framework\TestCase;

/**
 * A backend {@see \JesseGall\CodeCommandments\Ast\NodeMatch} must expose its position
 * AS a {@see Span} — the seam the scribe layer rewrites through, identical to the
 * frontend {@see \JesseGall\CodeCommandments\Vue\ElementMatch::span()}.
 */
final class NodeMatchSpanTest extends TestCase
{
    public function test_span_covers_exactly_the_matched_node_source(): void
    {
        $code = <<<'PHP'
        <?php
        $x = new Foo('a', 'b');
        PHP;

        $match = Codebase::fromString($code)->whereNew('Foo')->first();
        $this->assertNotNull($match);

        $span = $match->span();

        $this->assertInstanceOf(Span::class, $span);
        $this->assertSame("new Foo('a', 'b')", $span->text());
        $this->assertSame('memory.php', $span->path);
        // The source the span carries is the whole file, so a scribe can splice it.
        $this->assertSame($code, $span->source);
    }

    public function test_span_offsets_are_exclusive_end_so_a_splice_round_trips(): void
    {
        $code = <<<'PHP'
        <?php
        return new Bar();
        PHP;

        $span = Codebase::fromString($code)->whereNew('Bar')->first()->span();

        // Splice the span out and back in — must reproduce the file byte-for-byte.
        $rebuilt = substr($code, 0, $span->start) . $span->text() . substr($code, $span->end);
        $this->assertSame($code, $rebuilt);
    }
}
