<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

use JesseGall\CodeCommandments\Py\Expr\Expr;

/**
 * A `while test:` loop, its `else` block run when the test turns false without a `break`.
 */
final class WhileLoop extends Node
{
    public function __construct(
        public readonly Expr $test,
        public readonly Block $body,
        public readonly ?Block $else = null,
    ) {}

    public function children(): array
    {
        return $this->else === null ? [$this->body] : [$this->body, $this->else];
    }

    public function expressions(): array
    {
        return [$this->test];
    }
}
