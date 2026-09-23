<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Py;

use JesseGall\CodeCommandments\Py\Comment;
use JesseGall\CodeCommandments\Py\ModuleFile;
use JesseGall\CodeCommandments\Py\Node\ClassDef;
use JesseGall\CodeCommandments\Py\Node\FunctionDef;
use JesseGall\CodeCommandments\Py\Node\Node;
use PHPUnit\Framework\TestCase;

final class CommentsTest extends TestCase
{
    private const string SOURCE = <<<'PY'
        # the shop's pricing
        RATE = 3  # a trailing note


        # formerly lived in checkout
        # moved here in the refactor
        def price(order):
            """Price an order."""
            # add the rate
            return order.total + RATE


        class Cart:
            '''A basket of lines.

            Held per session.
            '''

            def empty(self):
                pass
        PY;

    public function test_every_comment_keeps_its_text_and_span(): void
    {
        $module = ModuleFile::fromFile(self::SOURCE, 'shop.py');

        $this->assertSame(
            ["the shop's pricing", 'a trailing note', 'formerly lived in checkout', 'moved here in the refactor', 'add the rate'],
            array_map(static fn (Comment $comment): string => $comment->text, $module->comments()),
        );
        $this->assertSame('# a trailing note', substr(self::SOURCE, $module->comments()[1]->start, $module->comments()[1]->end - $module->comments()[1]->start));
    }

    public function test_the_run_of_own_line_comments_directly_above_a_statement(): void
    {
        $module = ModuleFile::fromFile(self::SOURCE, 'shop.py');
        $price = $this->first($module, FunctionDef::class);
        $return = $price->body->body[1];

        $this->assertSame(['formerly lived in checkout', 'moved here in the refactor'], $this->texts($module->commentsAbove($price)));
        $this->assertSame(['add the rate'], $this->texts($module->commentsAbove($return)));
        $this->assertSame([], $this->texts($module->commentsAbove($this->first($module, ClassDef::class))));
    }

    public function test_a_trailing_comment_is_no_comment_above_the_next_line(): void
    {
        $module = ModuleFile::fromFile("RATE = 3  # a trailing note\nTAX = 2\n", 'shop.py');

        $this->assertSame([], $module->commentsAbove($module->module->body[1]));
    }

    public function test_a_block_reads_its_docstring(): void
    {
        $module = ModuleFile::fromFile(self::SOURCE, 'shop.py');

        $this->assertSame('Price an order.', $this->first($module, FunctionDef::class)->body->docstring()->unwrap());
        $this->assertStringStartsWith("A basket of lines.\n", $this->first($module, ClassDef::class)->body->docstring()->unwrap());
        $functions = array_values(array_filter($module->nodes(), static fn (Node $node): bool => $node instanceof FunctionDef));
        $this->assertTrue($functions[1]->body->docstring()->isNone());
    }

    /**
     * @template T of Node
     * @param  class-string<T>  $class
     * @return T
     */
    private function first(ModuleFile $module, string $class): Node
    {
        return array_values(array_filter($module->nodes(), static fn (Node $node): bool => $node instanceof $class))[0];
    }

    /**
     * @param  list<Comment>  $comments
     * @return list<string>
     */
    private function texts(array $comments): array
    {
        return array_map(static fn (Comment $comment): string => $comment->text, $comments);
    }
}
