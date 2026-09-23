<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

use JesseGall\CodeCommandments\Py\Expr\Expr;

/**
 * A `class` with its decorators, bases (keyword arguments such as `metaclass=` among them) and body.
 */
final class ClassDef extends Node
{
    /**
     * @param  list<Expr>  $bases
     * @param  list<Expr>  $decorators
     */
    public function __construct(
        public readonly string $name,
        public readonly array $bases,
        public readonly Block $body,
        public readonly array $decorators = [],
    ) {}

    public function children(): array
    {
        return [$this->body];
    }

    public function expressions(): array
    {
        return [...$this->decorators, ...$this->bases];
    }

    public function declaredNames(): array
    {
        return [$this->name];
    }
}
