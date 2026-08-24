<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ast;

use JesseGall\CodeCommandments\Ast\Codebase;
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

    public function test_an_intersection_type_is_rendered_as_one(): void
    {
        $method = Codebase::fromString(<<<'PHP'
        <?php
        namespace App;
        class S { public function m(): Countable&Traversable { return $this->it; } }
        PHP)->whereMethodDeclaration()->get()[0];

        // It used to fall through and hand back the php-parser class name, so `A&B` read as the literal
        // `PhpParser\Node\IntersectionType` — a type nothing could ever match.
        $this->assertSame('App\Countable&App\Traversable', $method->returnTypeName());
    }

    public function test_a_type_this_cannot_name_is_rendered_as_nothing(): void
    {
        $this->assertSame('', TypeName::render(null));
    }

    public function test_an_undeclared_type_has_said_nothing_and_so_rules_nothing_out(): void
    {
        $this->assertTrue(TypeName::overlaps('', 'array'));
        $this->assertTrue(TypeName::overlaps('array', ''));
    }
}
