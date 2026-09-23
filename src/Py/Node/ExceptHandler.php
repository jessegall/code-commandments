<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

use JesseGall\CodeCommandments\Py\Expr\Expr;

/**
 * One `except` clause: the exception type it catches (none for a bare `except:`), the name it binds,
 * and its block. `except*` catches from an exception group.
 */
final class ExceptHandler extends Node
{
    public function __construct(
        public readonly ?Expr $type,
        public readonly ?string $name,
        public readonly Block $body,
        public readonly bool $group = false,
    ) {}

    public function children(): array
    {
        return [$this->body];
    }

    public function expressions(): array
    {
        return self::present([$this->type]);
    }

    public function variant(): string
    {
        return $this->group ? 'except*' : 'except';
    }
}
