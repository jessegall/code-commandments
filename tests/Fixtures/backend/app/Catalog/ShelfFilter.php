<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\ConstructorSideEffect;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Narrows a query it was handed, while being constructed. The caller still holds that builder, so
 * building this object silently changed an object somewhere else — and building it twice narrows
 * twice.
 */
#[Sinful(ConstructorSideEffect::class)]
final class ShelfFilter
{
    private readonly string $shelf;

    public function __construct(ShelfQuery $query, string $shelf, private readonly bool $inStockOnly)
    {
        $query->restrictTo($shelf);

        $this->shelf = $shelf;
    }

    public function describe(): string
    {
        return $this->inStockOnly ? "{$this->shelf} (in stock)" : $this->shelf;
    }
}

/**
 * A query that can be narrowed — narrowing changes the query itself, which is why doing it inside
 * someone else's constructor is felt elsewhere.
 */
final class ShelfQuery
{
    private string $shelf = '';

    public function restrictTo(string $shelf): void
    {
        $this->shelf = $shelf;
    }

    public function shelf(): string
    {
        return $this->shelf;
    }
}
