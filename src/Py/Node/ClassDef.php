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

    public function isScope(): bool
    {
        return true;
    }

    public function expressions(): array
    {
        return [...$this->decorators, ...$this->bases];
    }

    public function declaredNames(): array
    {
        return [$this->name];
    }

    /**
     * The literal values the class body gives its names — `PAID = "paid"` — as literal keys.
     *
     * @return list<string>
     */
    public function memberValueKeys(): array
    {
        $members = array_filter($this->body->body, static fn (Node $statement): bool => $statement instanceof Assign && count($statement->targets) === 1);

        return array_values(array_filter(array_map(static fn (Assign $member): string => $member->value->literalKey(), $members)));
    }
}
