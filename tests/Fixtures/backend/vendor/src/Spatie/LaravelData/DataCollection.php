<?php

namespace Spatie\LaravelData;

class DataCollection implements \Illuminate\Contracts\Support\Responsable, \Spatie\LaravelData\Contracts\BaseDataCollectable, \Spatie\LaravelData\Contracts\TransformableData, \Spatie\LaravelData\Contracts\ResponsableData, \Spatie\LaravelData\Contracts\IncludeableData, \Spatie\LaravelData\Contracts\WrappableData, \Spatie\LaravelData\Contracts\ContextableData, \IteratorAggregate, \Countable, \ArrayAccess, \Illuminate\Contracts\Database\Eloquent\Castable, \Illuminate\Contracts\Support\Arrayable, \Illuminate\Contracts\Support\Jsonable, \JsonSerializable, \Traversable
{
    use \Spatie\LaravelData\Concerns\BaseDataCollectable, \Spatie\LaravelData\Concerns\ResponsableData, \Spatie\LaravelData\Concerns\IncludeableData, \Spatie\LaravelData\Concerns\WrappableData, \Spatie\LaravelData\Concerns\TransformableData, \Spatie\LaravelData\Concerns\ContextableData, \Illuminate\Support\Traits\Macroable, \Spatie\LaravelData\Concerns\EnumerableMethods;
    protected \Illuminate\Support\Enumerable $items;
    public readonly string $dataClass;
    public function __construct(string $dataClass, \Illuminate\Support\Enumerable|\Spatie\LaravelData\DataCollection|array|null $items) {}
    public function items(): array {}
    public function toCollection(): \Illuminate\Support\Enumerable {}
    public function offsetExists($offset): bool {}
    public function offsetGet($offset): mixed {}
    public function offsetSet($offset, $value): void {}
    public function offsetUnset($offset): void {}
    public static function castUsing(array $arguments) {}
}
