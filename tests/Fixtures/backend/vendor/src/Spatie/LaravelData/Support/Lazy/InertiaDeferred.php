<?php

namespace Spatie\LaravelData\Support\Lazy;

class InertiaDeferred extends \Spatie\LaravelData\Support\Lazy\ConditionalLazy
{
    private ?string $group;
    public function __construct(mixed $value, ?string $group = NULL) {}
    public function resolve(): \Inertia\DeferProp {}
}
