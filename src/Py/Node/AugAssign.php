<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

use JesseGall\CodeCommandments\Py\Expr\Expr;

/**
 * `total += value` — a target updated by an operator.
 */
final class AugAssign extends Node
{
    public function __construct(
        public readonly Expr $target,
        public readonly string $operator,
        public readonly Expr $value,
    ) {}

    public function expressions(): array
    {
        return [$this->target, $this->value];
    }

    public function variant(): string
    {
        return $this->operator;
    }

    public function writtenTargets(): array
    {
        return [$this->target];
    }
}
