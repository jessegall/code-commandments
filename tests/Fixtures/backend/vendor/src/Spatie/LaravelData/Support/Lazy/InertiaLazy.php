<?php

namespace Spatie\LaravelData\Support\Lazy;

class InertiaLazy extends \Spatie\LaravelData\Support\Lazy\ConditionalLazy
{
    public function __construct(\Closure $value) {}
    public function resolve(): \Inertia\LazyProp|\Inertia\OptionalProp {}
}
