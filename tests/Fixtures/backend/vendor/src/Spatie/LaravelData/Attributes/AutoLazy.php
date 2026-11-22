<?php

namespace Spatie\LaravelData\Attributes;

#[\Attribute(9)]
class AutoLazy
{
    public function build(\Closure $castValue, mixed $payload, \Spatie\LaravelData\Support\DataProperty $property, mixed $value): \Spatie\LaravelData\Lazy {}
}
