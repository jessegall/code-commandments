<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ts\Expr;

/**
 * The type of a primitive TypeScript literal, named as TypeScript names it — a closed set, so a reader
 * asks the type rather than comparing strings.
 */
enum LiteralType: string
{
    case Boolean = 'boolean';
    case Null = 'null';
    case Undefined = 'undefined';
    case String = 'string';
    case Number = 'number';

    /**
     * Is a literal of this type data — a value that could be any other of its kind — rather than a
     * constant that carries meaning, as `true` and `null` do?
     */
    public function isData(): bool
    {
        return match ($this) {
            self::String, self::Number => true,
            self::Boolean, self::Null, self::Undefined => false,
        };
    }
}
