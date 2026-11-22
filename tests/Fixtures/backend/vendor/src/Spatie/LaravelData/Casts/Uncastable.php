<?php

namespace Spatie\LaravelData\Casts;

class Uncastable
{
    public static \Spatie\LaravelData\Casts\Uncastable $instance;
    private function __construct() {}
    public static function create(): \Spatie\LaravelData\Casts\Uncastable {}
}
