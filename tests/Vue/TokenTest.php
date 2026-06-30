<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Vue;

use JesseGall\CodeCommandments\Vue\Token;
use PHPUnit\Framework\TestCase;

final class TokenTest extends TestCase
{
    public function test_group_brackets_exclude_angles_while_type_brackets_include_them(): void
    {
        // The distinction the readers depend on: in an EXPRESSION `<`/`>` are operators, so a
        // call-argument span must not count them; in a TYPE a generic's `<…>` balances.
        $this->assertTrue(Token::opensGroup(Token::PAREN_OPEN));
        $this->assertTrue(Token::opensGroup(Token::BRACE_OPEN));
        $this->assertFalse(Token::opensGroup(Token::ANGLE_OPEN), 'an angle is an operator in an expression');

        $this->assertTrue(Token::opensType(Token::ANGLE_OPEN), 'a generic angle balances in a type');

        $this->assertTrue(Token::closesGroup(Token::BRACE_CLOSE));
        $this->assertFalse(Token::closesGroup(Token::ANGLE_CLOSE));
        $this->assertTrue(Token::closesType(Token::ANGLE_CLOSE));
    }
}
