<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Py;

use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Expr\ExprKind;
use JesseGall\CodeCommandments\Py\Expr\Parser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Python expressions read into a kind-tagged tree, the way the TypeScript engine reads its own: a
 * precedence climb from lambda down to the atoms, with calls, attributes and subscripts as trailers.
 * What the grammar does not model degrades to an Unknown node rather than throwing.
 */
final class ExprParserTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function literals(): iterable
    {
        yield 'integer' => ['42', 'number', '42'];
        yield 'float' => ['3.5', 'number', '3.5'];
        yield 'string' => ["'paid'", 'string', 'paid'];
        yield 'double-quoted' => ['"paid"', 'string', 'paid'];
        yield 'triple-quoted' => ["'''a\nb'''", 'string', "a\nb"];
        yield 'raw' => ['r"\\d+"', 'string', '\\d+'];
        yield 'bytes' => ["b'x'", 'bytes', 'x'];
        yield 'implicit concatenation' => ["'a' 'b'", 'string', 'ab'];
        yield 'true' => ['True', 'bool', 'True'];
        yield 'none' => ['None', 'none', 'None'];
        yield 'ellipsis' => ['...', 'ellipsis', '...'];
    }

    #[DataProvider('literals')]
    public function test_a_literal_knows_its_type_and_value(string $source, string $type, string $value): void
    {
        $literal = Parser::parse($source);

        $this->assertSame(ExprKind::Literal, $literal->kind);
        $this->assertSame($type, $literal->literalType()?->value);
        $this->assertSame($value, $literal->get('value'));
    }

    public function test_an_f_string_is_its_own_kind(): void
    {
        $this->assertSame(ExprKind::FString, Parser::parse('f"{x!r}"')->kind);
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function fStrings(): iterable
    {
        yield 'text, a field, its conversion and spec' => ['f"total: {order.total!r:>10}"', ['literal total: ', 'attribute(name order).total', 'literal !r:>10']];
        yield 'escaped braces are text' => ['f"{{a}} {x}"', ['literal {a} ', 'name x']];
        yield 'the other quote inside a field' => ["f\"{d['k']}\"", ['subscript(name d)[literal k]']];
        yield 'a field inside the spec' => ['f"{value:>{width}}"', ['name value', 'literal :>', 'name width']];
        yield 'concatenated with a plain string' => ["'a' f\"{b}\"", ['literal a', 'name b']];
        yield 'a comparison inside a field is not a conversion' => ['f"{a != b}"', ['compare']];
    }

    /**
     * @param  list<string>  $parts
     */
    #[DataProvider('fStrings')]
    public function test_an_f_string_holds_its_text_and_its_fields_in_order(string $source, array $parts): void
    {
        $this->assertSame($parts, array_map(fn (Expr $part): string => $this->describe($part), Parser::parse($source)->get('parts')));
    }

    public function test_a_nested_f_string_holds_its_own_parts(): void
    {
        $call = Parser::parse("f\"{plural(n, f'unread {kind}')}\"")->get('parts')[0];
        $inner = $call->get('arguments')[1];

        $this->assertSame(ExprKind::FString, $inner->kind);
        $this->assertSame(['literal unread ', 'name kind'], array_map(fn (Expr $part): string => $this->describe($part), $inner->get('parts')));
    }

    public function test_a_field_knows_where_it_is_in_the_file(): void
    {
        $source = 'label = f"due {order.total} now"';
        $field = Parser::parse(substr($source, 8), 8)->get('parts')[1];

        $this->assertSame('order.total', substr($source, $field->start, $field->end - $field->start));
    }

    public function test_trailers_chain_left_to_right(): void
    {
        $this->assertSame('call(attribute(subscript(attribute(name request).rows)[literal 0]).get)(literal id)', $this->shape('request.rows[0].get("id")'));
    }

    public function test_a_slice_keeps_its_three_parts(): void
    {
        $slice = Parser::parse('rows[::-1]')->get('index');

        $this->assertSame(ExprKind::Slice, $slice->kind);
        $this->assertNull($slice->get('lower'));
        $this->assertNull($slice->get('upper'));
        $this->assertSame('unary(- literal 1)', $this->describe($slice->get('step')));
    }

    public function test_a_call_reads_positional_keyword_and_unpacked_arguments(): void
    {
        $this->assertSame('call(name f)(literal 1, keyword timeout=(literal 5), *(name rest), **(name options))', $this->shape('f(1, timeout=5, *rest, **options)'));
    }

    public function test_a_lambda_keeps_its_parameters_and_body(): void
    {
        $lambda = Parser::parse('lambda row, default=None: row.get("id") or default');

        $this->assertSame(ExprKind::Lambda, $lambda->kind);
        $this->assertSame(['row', 'default'], array_map(static fn (Expr $param): string => $param->get('name'), $lambda->get('params')));
        $this->assertSame(ExprKind::Binary, $lambda->get('body')->kind);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function precedence(): iterable
    {
        yield 'boolean operators' => ['not a and b or c', 'binary(binary(unary(not name a) and name b) or name c)'];
        yield 'arithmetic' => ['1 + 2 * 3 ** 2', 'binary(literal 1 + binary(literal 2 * binary(literal 3 ** literal 2)))'];
        yield 'power binds before a sign' => ['-x ** 2', 'unary(- binary(name x ** literal 2))'];
        yield 'conditional' => ['a if ready else b', 'conditional(name ready ? name a : name b)'];
        yield 'bitwise' => ['a | b & c', 'binary(name a | binary(name b & name c))'];
        yield 'await' => ['await load(page)', 'unary(await call(name load)(name page))'];
    }

    #[DataProvider('precedence')]
    public function test_operators_bind_as_python_binds_them(string $source, string $shape): void
    {
        $this->assertSame($shape, $this->shape($source));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function comparisons(): iterable
    {
        yield 'is not' => ['value is not None', ['is not']];
        yield 'not in' => ['key not in seen', ['not in']];
        yield 'chained' => ['0 < n <= limit', ['<', '<=']];
    }

    /**
     * @param  list<string>  $operators
     */
    #[DataProvider('comparisons')]
    public function test_a_comparison_keeps_every_operator_of_its_chain(string $source, array $operators): void
    {
        $compare = Parser::parse($source);

        $this->assertSame(ExprKind::Compare, $compare->kind);
        $this->assertSame($operators, $compare->get('operators'));
        $this->assertCount(count($operators) + 1, $compare->get('operands'));
    }

    public function test_a_walrus_binds_a_name(): void
    {
        $this->assertSame('walrus(name n := call(name len)(name rows))', $this->shape('(n := len(rows))'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function displays(): iterable
    {
        yield 'bare tuple' => ['a, b', 'tuple(name a, name b)'];
        yield 'one-element tuple' => ['(a,)', 'tuple(name a)'];
        yield 'empty tuple' => ['()', 'tuple()'];
        yield 'list with unpacking' => ['[1, *rest]', 'list(literal 1, *(name rest))'];
        yield 'set' => ['{1, 2}', 'set(literal 1, literal 2)'];
        yield 'dict with unpacking' => ['{"a": 1, **extra}', 'dict(literal a: literal 1, **(name extra))'];
        yield 'empty dict' => ['{}', 'dict()'];
    }

    #[DataProvider('displays')]
    public function test_displays_read_their_elements(string $source, string $shape): void
    {
        $this->assertSame($shape, $this->shape($source));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function comprehensions(): iterable
    {
        yield 'list' => ['[x for x in rows if x]', 'comprehension list(name x for name x in name rows if name x)'];
        yield 'dict' => ['{k: v for k, v in d.items()}', 'comprehension dict(name k: name v for tuple(name k, name v) in call(attribute(name d).items)())'];
        yield 'generator' => ['(r.id for r in rows)', 'comprehension generator(attribute(name r).id for name r in name rows)'];
        yield 'nested' => ['[c for row in grid for c in row]', 'comprehension list(name c for name row in name grid for name c in name row)'];
    }

    #[DataProvider('comprehensions')]
    public function test_comprehensions_read_their_element_and_clauses(string $source, string $shape): void
    {
        $this->assertSame($shape, $this->shape($source));
    }

    public function test_a_generator_as_the_only_argument_needs_no_parentheses(): void
    {
        $this->assertSame('call(name sum)(comprehension generator(attribute(name r).total for name r in name rows))', $this->shape('sum(r.total for r in rows)'));
    }

    public function test_yield_reads_its_value(): void
    {
        $this->assertSame('yield from(name rows)', $this->shape('(yield from rows)'));
    }

    public function test_an_unfinished_expression_degrades_rather_than_throws(): void
    {
        $this->assertInstanceOf(Expr::class, Parser::parse('rows[0].'));
        $this->assertInstanceOf(Expr::class, Parser::parse('f(a, '));
    }

    public function test_every_expression_knows_where_it_is(): void
    {
        $source = 'total = order.lines[0].price';
        $attribute = Parser::parse('order.lines[0].price', 8);

        $this->assertSame('order.lines[0].price', substr($source, $attribute->start, $attribute->end - $attribute->start));
    }

    private function shape(string $source): string
    {
        return $this->describe(Parser::parse($source));
    }

    /**
     * A compact rendering of the tree, so a test reads as the shape it expects.
     */
    private function describe(?Expr $expr): string
    {
        if ($expr === null) {
            return '_';
        }

        $list = fn (array $items): string => implode(', ', array_map(fn (Expr $item): string => $this->describe($item), $items));

        return match ($expr->kind) {
            ExprKind::Name => 'name ' . $expr->get('name'),
            ExprKind::Literal => 'literal ' . $expr->get('value'),
            ExprKind::Attribute => 'attribute(' . $this->describe($expr->get('object')) . ').' . $expr->get('name'),
            ExprKind::Subscript => 'subscript(' . $this->describe($expr->get('object')) . ')[' . $this->describe($expr->get('index')) . ']',
            ExprKind::Call => 'call(' . $this->describe($expr->get('callee')) . ')(' . $list($expr->get('arguments')) . ')',
            ExprKind::Keyword => 'keyword ' . $expr->get('name') . '=(' . $this->describe($expr->get('value')) . ')',
            ExprKind::Starred => ($expr->get('double') ? '**(' : '*(') . $this->describe($expr->get('value')) . ')',
            ExprKind::Unary => 'unary(' . $expr->get('op') . ' ' . $this->describe($expr->get('operand')) . ')',
            ExprKind::Binary => 'binary(' . $this->describe($expr->get('left')) . ' ' . $expr->get('op') . ' ' . $this->describe($expr->get('right')) . ')',
            ExprKind::Conditional => 'conditional(' . $this->describe($expr->get('test')) . ' ? ' . $this->describe($expr->get('then')) . ' : ' . $this->describe($expr->get('else')) . ')',
            ExprKind::Walrus => 'walrus(' . $this->describe($expr->get('target')) . ' := ' . $this->describe($expr->get('value')) . ')',
            ExprKind::Tuple => 'tuple(' . $list($expr->get('elements')) . ')',
            ExprKind::List => 'list(' . $list($expr->get('elements')) . ')',
            ExprKind::Set => 'set(' . $list($expr->get('elements')) . ')',
            ExprKind::Dict => 'dict(' . implode(', ', array_map(
                fn (?Expr $key, Expr $value): string => $key === null ? $this->describe($value) : $this->describe($key) . ': ' . $this->describe($value),
                $expr->get('keys'),
                $expr->get('values'),
            )) . ')',
            ExprKind::Comprehension => 'comprehension ' . $expr->get('of') . '(' . ($expr->get('key') !== null ? $this->describe($expr->get('key')) . ': ' : '') . $this->describe($expr->get('element'))
                . implode('', array_map(fn (Expr $for): string => ' for ' . $this->describe($for->get('target')) . ' in ' . $this->describe($for->get('iterable'))
                    . implode('', array_map(fn (Expr $condition): string => ' if ' . $this->describe($condition), $for->get('conditions'))), $expr->get('clauses'))) . ')',
            ExprKind::Yield => ($expr->get('from') ? 'yield from(' : 'yield(') . $this->describe($expr->get('value')) . ')',
            default => $expr->kind->value,
        };
    }
}
