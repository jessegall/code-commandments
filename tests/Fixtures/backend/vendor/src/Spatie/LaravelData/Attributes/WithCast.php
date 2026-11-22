<?php

namespace Spatie\LaravelData\Attributes;

#[\Attribute(9)]
class WithCast implements \Spatie\LaravelData\Attributes\GetsCast
{
    public array $arguments;
    public string $castClass;
    public function __construct(string $castClass, mixed ...$arguments) {}
    public function get(): \Spatie\LaravelData\Casts\Cast {}
}
