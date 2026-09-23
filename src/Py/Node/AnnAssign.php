<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

use JesseGall\CodeCommandments\Py\Expr\Expr;

/**
 * `limit: int = 10` — a target, its annotation, and the value when one is given.
 */
final class AnnAssign extends Node
{
    public function __construct(
        public readonly Expr $target,
        public readonly Expr $annotation,
        public readonly ?Expr $value = null,
    ) {}

    public function expressions(): array
    {
        return self::present([$this->target, $this->value]);
    }
}
