<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Py;

use JesseGall\CodeCommandments\Py\Expr\ExprKind;
use JesseGall\CodeCommandments\Py\Node\Assign;
use JesseGall\CodeCommandments\Py\Node\AugAssign;
use JesseGall\CodeCommandments\Py\Node\AnnAssign;
use JesseGall\CodeCommandments\Py\Node\ClassDef;
use JesseGall\CodeCommandments\Py\Node\ExceptHandler;
use JesseGall\CodeCommandments\Py\Node\ExprStmt;
use JesseGall\CodeCommandments\Py\Node\ForLoop;
use JesseGall\CodeCommandments\Py\Node\FunctionDef;
use JesseGall\CodeCommandments\Py\Node\IfStmt;
use JesseGall\CodeCommandments\Py\Node\Import;
use JesseGall\CodeCommandments\Py\Node\Jump;
use JesseGall\CodeCommandments\Py\Node\MatchStmt;
use JesseGall\CodeCommandments\Py\Node\Module;
use JesseGall\CodeCommandments\Py\Node\Node;
use JesseGall\CodeCommandments\Py\Node\Raise;
use JesseGall\CodeCommandments\Py\Node\Return_;
use JesseGall\CodeCommandments\Py\Node\Simple;
use JesseGall\CodeCommandments\Py\Node\TryStmt;
use JesseGall\CodeCommandments\Py\Node\WhileLoop;
use JesseGall\CodeCommandments\Py\Node\With;
use JesseGall\CodeCommandments\Py\Parser;
use PHPUnit\Framework\TestCase;

/**
 * Python statements read into a module tree: every compound statement with the block it owns,
 * every simple statement with the expressions it holds. Total — a statement the grammar cannot read
 * is stepped over to its line end, never thrown on.
 */
final class ParserTest extends TestCase
{
    public function test_a_function_keeps_its_decorators_parameters_return_and_body(): void
    {
        $function = $this->only(<<<'PY'
            @route("POST", "/orders")
            async def create(self, order: Order, /, *rows, limit: int = 10, **options) -> Order | None:
                return order
            PY);

        $this->assertInstanceOf(FunctionDef::class, $function);
        $this->assertSame('create', $function->name);
        $this->assertTrue($function->async);
        $this->assertCount(1, $function->decorators);
        $this->assertSame(['self', 'order', 'rows', 'limit', 'options'], array_map(static fn ($param): string => $param->name, $function->params));
        $this->assertSame(['', '', '*', '', '**'], array_map(static fn ($param): string => $param->kind, $function->params));
        $this->assertNotNull($function->params[3]->default);
        $this->assertSame(ExprKind::Binary, $function->returns?->kind);
        $this->assertInstanceOf(Return_::class, $function->body->body[0]);
    }

    public function test_a_block_ends_with_its_last_statement_not_at_the_next_line(): void
    {
        $source = "class Cart:\n    def clear(self):\n        self.lines = []\n\n\n    def total(self):\n        return 0\n\n\nx = 1\n";
        [$cart] = Parser::module($source)->body;

        $this->assertSame('return 0', substr($source, $cart->end - 8, 8), 'a class ends where its last method does');
        $this->assertStringEndsWith('self.lines = []', substr($source, 0, $cart->body->body[0]->end), 'a method ends at its last statement, not the blank lines after');
    }

    public function test_a_class_keeps_its_bases_and_methods(): void
    {
        $class = $this->only(<<<'PY'
            class Controller(Stored, Files, metaclass=Meta):
                help = """Many
                lines"""

                def run(self):
                    pass
            PY);

        $this->assertInstanceOf(ClassDef::class, $class);
        $this->assertSame('Controller', $class->name);
        $this->assertCount(3, $class->bases);
        $this->assertInstanceOf(Assign::class, $class->body->body[0]);
        $this->assertInstanceOf(FunctionDef::class, $class->body->body[1]);
    }

    public function test_an_if_chains_its_elifs_and_else(): void
    {
        $if = $this->only("if a:\n    x()\nelif b:\n    y()\nelse:\n    z()\n");

        $this->assertInstanceOf(IfStmt::class, $if);
        $this->assertInstanceOf(IfStmt::class, $if->else);
        $this->assertNotNull($if->else->else);
    }

    public function test_loops_keep_their_else(): void
    {
        $for = $this->only("for key, value in rows.items():\n    if value:\n        break\nelse:\n    return None\n");
        $while = $this->only("while pending:\n    pending.pop()\nelse:\n    done()\n");

        $this->assertInstanceOf(ForLoop::class, $for);
        $this->assertSame(ExprKind::Tuple, $for->target->kind);
        $this->assertNotNull($for->else);
        $this->assertInstanceOf(WhileLoop::class, $while);
        $this->assertNotNull($while->else);
    }

    public function test_a_try_keeps_every_handler_else_and_finally(): void
    {
        $try = $this->only(<<<'PY'
            try:
                load()
            except (OSError, ValueError) as error:
                log(error)
            except Exception:
                pass
            else:
                done()
            finally:
                close()
            PY);

        $this->assertInstanceOf(TryStmt::class, $try);
        $this->assertCount(2, $try->handlers);
        $this->assertInstanceOf(ExceptHandler::class, $try->handlers[0]);
        $this->assertSame('error', $try->handlers[0]->name);
        $this->assertNotNull($try->else);
        $this->assertNotNull($try->finally);
    }

    public function test_a_with_reads_its_items_parenthesised_or_not(): void
    {
        $plain = $this->only("with open(path) as fh, lock:\n    fh.read()\n");
        $parenthesised = $this->only("with (\n    open(a) as x,\n    open(b) as y,\n):\n    pass\n");

        $this->assertInstanceOf(With::class, $plain);
        $this->assertCount(2, $plain->contexts);
        $this->assertCount(2, $parenthesised->contexts);
    }

    public function test_a_match_keeps_its_cases(): void
    {
        $match = $this->only("match command:\n    case Move(x, y) if x > 0:\n        go()\n    case _:\n        stop()\n");

        $this->assertInstanceOf(MatchStmt::class, $match);
        $this->assertCount(2, $match->cases);
        $this->assertNotNull($match->cases[0]->guard);
    }

    public function test_a_name_called_match_is_still_a_name(): void
    {
        $this->assertInstanceOf(Assign::class, $this->only("match = re.match(pattern, text)\n"));
    }

    /**
     * @return iterable<string, array{string, class-string<Node>}>
     */
    public static function simpleStatements(): iterable
    {
        yield 'assignment' => ['a = b = compute()', Assign::class];
        yield 'tuple assignment' => ['first, *rest = rows', Assign::class];
        yield 'augmented' => ['total += line.price', AugAssign::class];
        yield 'annotated' => ['limit: int = 10', AnnAssign::class];
        yield 'expression' => ['print(x)', ExprStmt::class];
        yield 'return' => ['return a, b', Return_::class];
        yield 'raise from' => ['raise Refused("no") from None', Raise::class];
        yield 'bare raise' => ['raise', Raise::class];
        yield 'import' => ['import os.path as p, sys', Import::class];
        yield 'from import' => ['from ..engine import (record, keeper as k)', Import::class];
        yield 'break' => ['break', Jump::class];
        yield 'pass' => ['pass', Simple::class];
        yield 'assert' => ['assert hook(x) == {"a": 1}, "message"', Simple::class];
        yield 'del' => ['del sys.modules[name]', Simple::class];
        yield 'global' => ['global counter', Simple::class];
        yield 'type alias' => ['type Rows = list[Row]', Simple::class];
    }

    /**
     * @param  class-string<Node>  $class
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('simpleStatements')]
    public function test_a_simple_statement_is_read_whole(string $source, string $class): void
    {
        $this->assertInstanceOf($class, $this->only($source . "\n"));
    }

    public function test_the_augmented_operator_is_the_statement_s_variant(): void
    {
        $this->assertSame('+=', $this->only("total += 1\n")->variant());
    }

    public function test_a_from_import_knows_its_module_level_and_names(): void
    {
        $import = $this->only("from ..engine.record import keeper as k, band\n");

        $this->assertSame('engine.record', $import->module);
        $this->assertSame(2, $import->level);
        $this->assertSame(['keeper' => 'k', 'band' => 'band'], $import->names);
    }

    public function test_simple_statements_share_a_line_through_semicolons(): void
    {
        $this->assertCount(3, Parser::module("a = 1; b = 2; c()\n")->body);
    }

    public function test_a_one_line_suite_holds_its_statement(): void
    {
        $if = $this->only("if not rows: return []\n");

        $this->assertInstanceOf(Return_::class, $if->body->body[0]);
    }

    public function test_a_function_body_is_its_function_body(): void
    {
        $function = $this->only("def f():\n    return 1\n");

        $this->assertTrue($function->functionBody()->isSome());
        $this->assertTrue($this->only("x = 1\n")->functionBody()->isNone());
    }

    public function test_every_statement_knows_where_it_is(): void
    {
        $source = "import os\n\ndef load():\n    return os.getcwd()\n";
        $function = Parser::module($source)->body[1];

        $this->assertStringStartsWith('def load():', substr($source, $function->start, $function->end - $function->start));
    }

    public function test_a_truncated_file_is_read_as_far_as_it_goes(): void
    {
        $module = Parser::module("def f(a, b:\n");

        $this->assertInstanceOf(Module::class, $module);
    }

    public function test_an_unreadable_statement_is_stepped_over(): void
    {
        $module = Parser::module("x = 1\n) ) )\ny = 2\n");

        $this->assertCount(2, array_filter($module->body, static fn (Node $node): bool => $node instanceof Assign));
    }

    private function only(string $source): Node
    {
        $body = Parser::module($source)->body;
        $this->assertCount(1, $body, 'the source holds one statement');

        return $body[0];
    }
}
