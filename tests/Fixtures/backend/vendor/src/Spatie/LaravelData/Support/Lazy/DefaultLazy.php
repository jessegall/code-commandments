<?php

namespace Spatie\LaravelData\Support\Lazy;

class DefaultLazy extends \Spatie\LaravelData\Lazy
{
    protected \Closure $value;
    protected function __construct(\Closure $value) {}
    public function resolve(): mixed {}
    public function __serialize(): array {}
    public function __unserialize(array $data): void {}
}
