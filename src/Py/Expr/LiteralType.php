<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Expr;

/**
 * What a Python literal holds — a closed set, so a reader asks the type rather than comparing strings.
 */
enum LiteralType: string
{
    case String = 'string';
    case Bytes = 'bytes';
    case Number = 'number';
    case Bool = 'bool';
    case None = 'none';
    case Ellipsis = 'ellipsis';
    /**
     * The text of an f-string field's format spec — `.2f` in `{price:.2f}`.
     */
    case Format = 'format';

    /**
     * Is a literal of this type data — a value that could be any other of its kind — rather than a
     * constant that carries meaning, as `True`, `None` and `...` do?
     */
    public function isData(): bool
    {
        return match ($this) {
            self::String, self::Bytes, self::Number, self::Format => true,
            self::Bool, self::None, self::Ellipsis => false,
        };
    }

    /**
     * Is a literal of this type text — a string or bytes?
     */
    public function isText(): bool
    {
        return match ($this) {
            self::String, self::Bytes => true,
            self::Number, self::Bool, self::None, self::Ellipsis, self::Format => false,
        };
    }
}
