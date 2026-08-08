<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

use JesseGall\CodeCommandments\Vue\Expr\Expr;

/**
 * A `throw` and what it raises.
 */
final class ThrowStmt extends Stmt
{
    public function __construct(public readonly Expr $value) {}

    public function expressions(): array
    {
        return [$this->value];
    }

    public function isThrow(): bool
    {
        return true;
    }

    public function render(): string
    {
        return 'throw ' . $this->value->source() . ';';
    }
}
