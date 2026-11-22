<?php

namespace Spatie\LaravelData\Support\Lazy;

class ConditionalLazy extends \Spatie\LaravelData\Lazy
{
    protected \Closure $condition;
    protected \Closure $value;
    protected function __construct(\Closure $condition, \Closure $value) {}
    public function resolve(): mixed {}
    public function shouldBeIncluded(): bool {}
    public function __serialize(): array {}
    public function __unserialize(array $data): void {}
}
