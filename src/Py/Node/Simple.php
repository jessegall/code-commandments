<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Expr\ExprKind;

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

    /**
     * The names a `global` statement declares — none for any other statement.
     *
     * @return list<string>
     */
    public function globalNames(): array
    {
        if ($this->keyword !== 'global') {
            return [];
        }

        $named = array_filter(array_merge([], ...array_map(static fn (Expr $held): array => $held->flatten(), $this->holds)), static fn (Expr $name): bool => $name->is(ExprKind::Name));

        return array_values(array_map(static fn (Expr $name): string => (string) $name->get('name'), $named));
    }
}
