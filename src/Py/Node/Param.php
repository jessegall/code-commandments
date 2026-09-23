<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

use JesseGall\CodeCommandments\Py\Expr\Expr;

/**
 * One parameter of a function: its name, its annotation and default when written, and its kind — `*`
 * for `*args`, `**` for `**kwargs`, empty for an ordinary one.
 */
final class Param extends Node
{
    public function __construct(
        public readonly string $name,
        public readonly string $kind = '',
        public readonly ?Expr $annotation = null,
        public readonly ?Expr $default = null,
    ) {}

    public function expressions(): array
    {
        return self::present([$this->default]);
    }

    public function variant(): string
    {
        return $this->kind;
    }

    public function declaredNames(): array
    {
        return [$this->name];
    }
}
