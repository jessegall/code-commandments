<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

use JesseGall\CodeCommandments\Py\Expr\Expr;

/**
 * A simple statement known by its keyword and the expressions it holds — `pass`, `assert`, `del`,
 * `global`, `nonlocal` and a `type` alias.
 */
final class Simple extends Node
{
    /**
     * @param  list<Expr>  $holds
     */
    public function __construct(
        public readonly string $keyword,
        public readonly array $holds = [],
    ) {}

    public function expressions(): array
    {
        return $this->holds;
    }

    public function variant(): string
    {
        return $this->keyword;
    }

    public function isPlaceholder(): bool
    {
        return $this->keyword === 'pass';
    }
}
