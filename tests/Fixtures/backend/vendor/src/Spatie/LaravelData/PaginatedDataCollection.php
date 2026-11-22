<?php

namespace Spatie\LaravelData;

class PaginatedDataCollection implements \Illuminate\Contracts\Support\Responsable, \Spatie\LaravelData\Contracts\BaseDataCollectable, \Spatie\LaravelData\Contracts\TransformableData, \Spatie\LaravelData\Contracts\ContextableData, \Spatie\LaravelData\Contracts\ResponsableData, \Spatie\LaravelData\Contracts\IncludeableData, \Spatie\LaravelData\Contracts\WrappableData, \IteratorAggregate, \Countable, \Illuminate\Contracts\Database\Eloquent\Castable, \Illuminate\Contracts\Support\Arrayable, \Illuminate\Contracts\Support\Jsonable, \JsonSerializable, \Traversable
{
    use \Spatie\LaravelData\Concerns\ResponsableData, \Spatie\LaravelData\Concerns\IncludeableData, \Spatie\LaravelData\Concerns\WrappableData, \Spatie\LaravelData\Concerns\TransformableData, \Spatie\LaravelData\Concerns\BaseDataCollectable, \Spatie\LaravelData\Concerns\ContextableData, \Illuminate\Support\Traits\Macroable;
    protected \Illuminate\Contracts\Pagination\Paginator $items;
    public readonly string $dataClass;
    public function __construct(string $dataClass, \Illuminate\Contracts\Pagination\Paginator $items) {}
    public function through(\Closure $through): static {}
    public function items(): \Illuminate\Contracts\Pagination\Paginator {}
    public static function castUsing(array $arguments) {}
    public function withoutWrapping(): static {}
}
