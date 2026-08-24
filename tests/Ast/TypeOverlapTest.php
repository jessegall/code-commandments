<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ast;

use JesseGall\CodeCommandments\Ast\TypeName;
use PHPUnit\Framework\TestCase;

final class TypeOverlapTest extends TestCase
{
    public function test_a_narrower_type_overlaps_the_wider_one_it_satisfies(): void
    {
        $this->assertTrue(TypeName::overlaps('array', 'iterable'));
        $this->assertTrue(TypeName::overlaps('iterable', 'array'));
        $this->assertTrue(TypeName::overlaps('string', 'false|string'));
        $this->assertTrue(TypeName::overlaps('static', 'self'));
    }

    public function test_types_that_can_never_hold_the_same_value_do_not_overlap(): void
    {
        $this->assertFalse(TypeName::overlaps('array', 'false|string'));
        $this->assertFalse(TypeName::overlaps('void', 'array'));
        $this->assertFalse(TypeName::overlaps('int', 'string'));
    }

    public function test_an_undeclared_type_has_said_nothing_and_so_rules_nothing_out(): void
    {
        $this->assertTrue(TypeName::overlaps('', 'array'));
        $this->assertTrue(TypeName::overlaps('array', ''));
    }
}
