<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use UnitEnum;

/**
 * A token of either lexer — the TypeScript {@see \JesseGall\CodeCommandments\Ts\Lexeme}, whose kind is a
 * string, and the Python {@see \JesseGall\CodeCommandments\Py\Token}, whose kind is an enum case — asked
 * whether it is of a kind, carrying a given text where one is asked for.
 */
trait TokenOfKind
{
    public function is(string|UnitEnum $kind, ?string $value = null): bool
    {
        return $this->kind === $kind && ($value === null || $this->value === $value);
    }
}
