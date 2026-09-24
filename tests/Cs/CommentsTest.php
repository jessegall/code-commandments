<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cs;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\Comment;
use JesseGall\CodeCommandments\Cs\CommentKind;
use JesseGall\CodeCommandments\Cs\Cref;
use PHPUnit\Framework\TestCase;

/**
 * The bridge hands over every comment in a file — its kind, its text and its span — and resolves each `cref` a
 * documentation comment names.
 */
final class CommentsTest extends TestCase
{
    use NeedsTheBridge;

    private const string SOURCE = <<<'CS'
        public sealed class Order
        {
            // the total in cents
            public int Cents { get; init; }

            /* kept for the export */
            public string Note { get; init; } = "";

            /// <summary>Copies this <see cref="Order"/>, unlike <see cref="Missing"/>.</summary>
            public Order Copy() => this;
        }
        CS;

    protected function setUp(): void
    {
        $this->requireTheBridge();
    }

    public function test_hands_over_each_comment_with_its_kind_in_order(): void
    {
        $comments = $this->comments();

        $this->assertSame([CommentKind::Line, CommentKind::Block, CommentKind::Documentation], array_map(static fn (Comment $comment): CommentKind => $comment->kind, $comments));
        $this->assertSame('// the total in cents', $comments[0]->text);
        $this->assertSame('/* kept for the export */', $comments[1]->text);
    }

    public function test_a_comments_span_is_where_it_stands_in_the_source(): void
    {
        foreach ($this->comments() as $comment) {
            $this->assertStringStartsWith(trim(substr(self::SOURCE, $comment->start, $comment->end - $comment->start)), trim($comment->text));
        }
    }

    public function test_a_documentation_comment_resolves_its_crefs(): void
    {
        $crefs = $this->comments()[2]->crefs;

        $this->assertSame(['Order', 'Missing'], array_map(static fn (Cref $cref): string => $cref->text, $crefs));
        $this->assertSame('global::Order', $crefs[0]->symbol()->unwrapOr('none'));
        $this->assertTrue($crefs[1]->symbol()->isNone());
    }

    /**
     * @return list<Comment>
     */
    private function comments(): array
    {
        return Codebase::fromString(self::SOURCE, 'Order.cs')->modules()[0]->comments();
    }
}
