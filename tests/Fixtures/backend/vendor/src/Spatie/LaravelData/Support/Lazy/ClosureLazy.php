<?php

namespace Spatie\LaravelData\Support\Lazy;

class ClosureLazy extends \Spatie\LaravelData\Support\Lazy\ConditionalLazy
{
    public function __construct(\Closure $closure) {}
    public function resolve(): \Closure {}
}
