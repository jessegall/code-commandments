<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ts\Node;

use JesseGall\CodeCommandments\Ts\Expr\Expr;

/**
 * A `return`, with the value it hands back — null for a bare `return;`.
 */
final class ReturnStmt extends Stmt
{
    public function __construct(public readonly ?Expr $value = null) {}

    public function expressions(): array
    {
        return $this->value !== null ? [$this->value] : [];
    }

    public function isReturn(): bool
    {
        return true;
    }

    /**
     * Does it hand back ABSENCE — `null` or `undefined` written literally? The read a rule about
     * swallowing a failure into a missing value makes.
     */
    public function returnsAbsence(): bool
    {
        return $this->value !== null && $this->value->isNullLiteral();
    }

    public function render(): string
    {
        return $this->value !== null ? 'return ' . $this->value->source() . ';' : 'return;';
    }
}
