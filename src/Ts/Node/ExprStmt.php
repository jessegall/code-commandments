<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ts\Node;

use JesseGall\CodeCommandments\Ts\Expr\Expr;

/**
 * An expression standing alone as a statement — a call, an assignment, an `await`. Most of what a
 * body DOES is one of these.
 */
final class ExprStmt extends Stmt
{
    public function __construct(public readonly Expr $expr) {}

    public function expressions(): array
    {
        return [$this->expr];
    }

    public function render(): string
    {
        return $this->expr->source() . ';';
    }
}
