<?php

namespace Spatie\LaravelData\Attributes;

#[\Attribute(9)]
class MapInputName
{
    public string|int $input;
    public function __construct(string|int $input) {}
}
