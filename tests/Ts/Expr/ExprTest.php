<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ts\Expr;

use JesseGall\CodeCommandments\Ts\Expr\Parser;
use PHPUnit\Framework\TestCase;

final class ExprTest extends TestCase
{
    public function test_roots_do_not_leak_an_arrow_parameter_bound_inside_the_expression(): void
    {
        // The bug: `list.filter((k) => k.type === 'product')` reported `k` as a free root, so an
        // extraction minted a bogus `k` prop typed `unknown`. An arrow parameter is bound INSIDE the
        // expression — the list is the only real read.
        $this->assertSame(['list'], Parser::parse("list.filter((k) => k.type === 'product')")->roots());
    }

    public function test_roots_subtract_every_arrow_parameter(): void
    {
        // Multi-param and single-param arrows alike bind their names locally.
        $this->assertSame(['items'], Parser::parse('items.reduce((acc, item) => acc + item.total, 0)')->roots());
        $this->assertSame(['rows'], Parser::parse('rows.map(row => row.id)')->roots());
    }

    public function test_roots_subtract_a_typescript_typed_arrow_parameter(): void
    {
        // The real leak: a TS-typed handler param — `(v: string | number) => …`. The type annotation
        // must not stop the parser from binding `v`, nor leak `v` as a free read. The bound param is
        // gone; the free reads that remain are the callees/reads (`Boundary` filters callees later).
        $this->assertSame(['updateEntry', 'row', 'Number'], Parser::parse('(v: string | number) => updateEntry(row.key, { count: Number(v) })')->roots());
        $this->assertSame(['emit'], Parser::parse('(val: boolean) => emit(val)')->roots());
        $this->assertSame([], Parser::parse('(a: number, b: string) => a + b')->roots());
    }

    public function test_roots_still_see_free_reads_alongside_an_arrow(): void
    {
        // An arrow body may legitimately read an outer variable — only the PARAMS are subtracted.
        $this->assertSame(['list', 'query'], Parser::parse('list.filter((k) => k.name === query)')->roots());
    }

    public function test_object_shape_infers_a_struct_from_a_literal(): void
    {
        // Each field takes its value's soundly-inferred type; a value only a checker could type
        // (a call, an identifier, a nested object) falls back to `unknown` — a partial but usable shape.
        $this->assertSame(
            '{ name: string; quantity: number; active: boolean; note: null; make: unknown }',
            Parser::parse('{ name: "", quantity: 0, active: true, note: null, make: build() }')->objectShape(),
        );
        $this->assertNull(Parser::parse('{}')->objectShape(), 'an empty object names nothing');
        $this->assertNull(Parser::parse('42')->objectShape(), 'a non-object has no shape');
    }

    public function test_callee_and_argument_read_a_call(): void
    {
        $call = Parser::parse('useForm({ name: "" })');

        $this->assertSame('useForm', $call->callee());
        $this->assertSame('{ name: string }', $call->argument(0)?->objectShape());
        $this->assertNull(Parser::parse('obj.method()')->callee(), 'a member call has no bare callee');
    }

    public function test_a_blank_comparison_is_read_whichever_side_the_literal_sits(): void
    {
        // #510: the question that proves a blank string is standing in for a missing value. Both
        // spellings of the empty literal, both equality operators, and either side.
        $this->assertTrue(Parser::parse("isolation === ''")->isBlankComparison());
        $this->assertTrue(Parser::parse('isolation !== ""')->isBlankComparison());
        $this->assertTrue(Parser::parse("'' === props.isolation")->isBlankComparison());

        $this->assertFalse(Parser::parse("isolation === 'none'")->isBlankComparison(), 'a value is not the blank');
        $this->assertFalse(Parser::parse('isolation === null')->isBlankComparison(), 'null is the other absence');
        $this->assertFalse(Parser::parse("isolation + ''")->isBlankComparison(), 'concatenation asks nothing');
        $this->assertFalse(Parser::parse("count === 0")->isBlankComparison(), 'zero is not blank');
    }

    public function test_a_blank_comparison_names_the_field_it_asks_about(): void
    {
        // The name is what crosses the wire, so a bare read and a property read answer alike — the
        // last hop of a data path IS the field.
        $this->assertSame('isolation', Parser::parse("isolation === ''")->comparisonSubject()->readName());
        $this->assertSame('isolation', Parser::parse("props.isolation !== ''")->comparisonSubject()->readName());
        $this->assertSame('isolation', Parser::parse("'' === form.data.isolation")->comparisonSubject()->readName());

        $this->assertSame('', Parser::parse("trim(isolation) === ''")->comparisonSubject()->readName(), 'a call is not a data path');
        $this->assertSame('', Parser::parse("'' === ''")->comparisonSubject()->readName(), 'two literals ask about nothing');
        $this->assertSame('', Parser::parse('isolation')->comparisonSubject()->readName(), 'not a comparison at all');
    }
}
