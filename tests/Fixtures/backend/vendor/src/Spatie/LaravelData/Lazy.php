<?php

namespace Spatie\LaravelData;

abstract class Lazy
{
    use \Illuminate\Support\Traits\Macroable;
    protected ?bool $defaultIncluded = NULL;
    public static function create(\Closure $value): \Spatie\LaravelData\Support\Lazy\DefaultLazy {}
    public static function when(\Closure $condition, \Closure $value): \Spatie\LaravelData\Support\Lazy\ConditionalLazy {}
    public static function whenLoaded(string $relation, \Illuminate\Database\Eloquent\Model $model, \Closure $value): \Spatie\LaravelData\Support\Lazy\RelationalLazy {}
    public static function inertia(\Closure $value): \Spatie\LaravelData\Support\Lazy\InertiaLazy {}
    public static function inertiaDeferred(mixed $value, ?string $group = NULL): \Spatie\LaravelData\Support\Lazy\InertiaDeferred {}
    public static function closure(\Closure $closure): \Spatie\LaravelData\Support\Lazy\ClosureLazy {}
    abstract public function resolve(): mixed;
    abstract public function __serialize(): array;
    abstract public function __unserialize(array $data): void;
    public function defaultIncluded(bool $defaultIncluded = true): \Spatie\LaravelData\Lazy {}
    public function isDefaultIncluded(): bool {}
    public function __get(string $name): mixed {}
    public function __call(string $name, array $arguments): mixed {}
}
