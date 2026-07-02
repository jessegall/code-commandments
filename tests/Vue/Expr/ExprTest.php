<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Vue\Expr;

use JesseGall\CodeCommandments\Vue\Expr\Parser;
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

    public function test_roots_still_see_free_reads_alongside_an_arrow(): void
    {
        // An arrow body may legitimately read an outer variable — only the PARAMS are subtracted.
        $this->assertSame(['list', 'query'], Parser::parse('list.filter((k) => k.name === query)')->roots());
    }
}
