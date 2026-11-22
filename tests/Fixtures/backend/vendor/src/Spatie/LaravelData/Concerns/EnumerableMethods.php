<?php

namespace Spatie\LaravelData\Concerns;

trait EnumerableMethods
{
    public function through(callable $through): static {}
    public function map(callable $map): static {}
    public function filter(callable $filter): static {}
    public function reject(callable $filter): static {}
    /** @return TValue|TFirstDefault */
    public function first(?callable $callback = NULL, $default = NULL) {}
    /** @return TValue|TLastDefault */
    public function last(?callable $callback = NULL, $default = NULL) {}
    public function each(callable $callback): static {}
    public function values(): static {}
    public function where(string $key, mixed $operator = NULL, mixed $value = NULL): static {}
    /** @return TReduceReturnType */
    public function reduce(callable $callback, mixed $initial = NULL) {}
    /** @return TValue */
    public function sole(callable|string|null $key = NULL, mixed $operator = NULL, mixed $value = NULL) {}
    public function merge(\Spatie\LaravelData\DataCollection $collection): static {}
}
