<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

use JesseGall\CodeCommandments\Py\Expr\Expr;

/**
 * A `for target in iterable:` loop, its `else` block run when it ends without a `break`.
 */
final class ForLoop extends Node
{
    public function __construct(
        public readonly Expr $target,
        public readonly Expr $iterable,
        public readonly Block $body,
        public readonly ?Block $else = null,
        public readonly bool $async = false,
    ) {}

    /**
     * `async` or nothing — an async one cannot share a body with its sync twin.
     */
    public function variant(): string
    {
        return $this->async ? 'async' : '';
    }

    public function children(): array
    {
        return $this->else === null ? [$this->body] : [$this->body, $this->else];
    }

    public function expressions(): array
    {
        return [$this->target, $this->iterable];
    }
}
