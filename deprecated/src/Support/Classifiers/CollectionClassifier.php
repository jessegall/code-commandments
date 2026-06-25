<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Classifiers;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;
use Illuminate\Support\LazyCollection;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\PaginatedDataCollection;

/**
 * Classifies collection-like types — those with an "empty identity": Laravel /
 * Spatie collections, Fluent, or anything implementing a countable/iterable/
 * array-access interface (so a `?Collection` is better defaulted to an empty
 * collection than left null).
 *
 * Types are named by `::class` (IDE-navigable, refactor-safe); only their SHORT
 * name is matched, so analysed code need not import the same class. `::class` is
 * a compile-time string — these vendor classes need not be installed at runtime.
 */
final class CollectionClassifier extends TypeClassifier
{
    protected function types(): array
    {
        return [
            Collection::class, LazyCollection::class, EloquentCollection::class,
            DataCollection::class, PaginatedDataCollection::class, Fluent::class,
        ];
    }

    protected function interfaces(): array
    {
        return [\Countable::class, \IteratorAggregate::class, \Traversable::class, \ArrayAccess::class, Arrayable::class];
    }
}
