<?php

namespace Spatie\LaravelData\Support\Lazy;

class RelationalLazy extends \Spatie\LaravelData\Lazy
{
    protected string $relation;
    protected \Illuminate\Database\Eloquent\Model $model;
    protected \Closure $value;
    protected function __construct(string $relation, \Illuminate\Database\Eloquent\Model $model, \Closure $value) {}
    public function resolve(): mixed {}
    public function shouldBeIncluded(): bool {}
    public function __serialize(): array {}
    public function __unserialize(array $data): void {}
}
