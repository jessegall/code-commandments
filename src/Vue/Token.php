<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

/**
 * Shared token vocabulary for script/expression readers. Two nesting modes: expression
 * (`()[]{}`, angles are operators) and type (`()[]{}<>`, generics balance). Pure vocabulary,
 * no state.
 */
final class Token
{
    // ---- kinds (as the Script lexer tags them) --------------------------------
    public const string IDENTIFIER = 'id';
    public const string PUNCTUATION = 'punct';
    public const string STRING = 'string';
    public const string NUMBER = 'num';

    // ---- punctuation ----------------------------------------------------------
    public const string PAREN_OPEN = '(';
    public const string PAREN_CLOSE = ')';
    public const string BRACE_OPEN = '{';
    public const string BRACE_CLOSE = '}';
    public const string BRACKET_OPEN = '[';
    public const string BRACKET_CLOSE = ']';
    public const string ANGLE_OPEN = '<';
    public const string ANGLE_CLOSE = '>';
    public const string SEMICOLON = ';';
    public const string COMMA = ',';
    public const string COLON = ':';
    public const string ASSIGN = '=';

    /**
     * Expression-level openers/closers — `()[]{}` (angles are operators, not delimiters).
     */
    public const array GROUP_OPEN = [self::PAREN_OPEN, self::BRACKET_OPEN, self::BRACE_OPEN];
    public const array GROUP_CLOSE = [self::PAREN_CLOSE, self::BRACKET_CLOSE, self::BRACE_CLOSE];

    /**
     * Type-level openers/closers — `()[]{}<>` (a generic's `<…>` balances in a type).
     */
    public const array TYPE_OPEN = [self::PAREN_OPEN, self::BRACKET_OPEN, self::BRACE_OPEN, self::ANGLE_OPEN];
    public const array TYPE_CLOSE = [self::PAREN_CLOSE, self::BRACKET_CLOSE, self::BRACE_CLOSE, self::ANGLE_CLOSE];

    public static function opensGroup(string $value): bool
    {
        return in_array($value, self::GROUP_OPEN, true);
    }

    public static function closesGroup(string $value): bool
    {
        return in_array($value, self::GROUP_CLOSE, true);
    }

    public static function opensType(string $value): bool
    {
        return in_array($value, self::TYPE_OPEN, true);
    }

    public static function closesType(string $value): bool
    {
        return in_array($value, self::TYPE_CLOSE, true);
    }
}
