<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

use JesseGall\CodeCommandments\Py\Expr\Expr;

/**
 * A `raise` — of an exception and the cause it is raised `from`, or bare, re-raising the one in hand.
 */
final class Raise extends Node
{
    public function __construct(
        public readonly ?Expr $exception = null,
        public readonly ?Expr $cause = null,
    ) {}

    public function expressions(): array
    {
        return self::present([$this->exception, $this->cause]);
    }
}
