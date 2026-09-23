<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

use JesseGall\CodeCommandments\Py\Expr\Expr;

/**
 * An `if` — its test, its block, and what follows it: an `elif` (another IfStmt), an `else` block, or nothing.
 */
final class IfStmt extends Node
{
    public function __construct(
        public readonly Expr $test,
        public readonly Block $body,
        public readonly IfStmt|Block|null $else = null,
    ) {}

    public function children(): array
    {
        return $this->else === null ? [$this->body] : [$this->body, $this->else];
    }

    public function expressions(): array
    {
        return [$this->test];
    }

    public function isBranchingConstruct(): bool
    {
        return true;
    }
}
