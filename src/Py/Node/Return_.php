<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

use JesseGall\CodeCommandments\Py\Expr\Expr;

/**
 * A `return`, with the value it returns when it has one.
 */
final class Return_ extends Node
{
    public function __construct(public readonly ?Expr $value = null) {}

    public function expressions(): array
    {
        return self::present([$this->value]);
    }
}
