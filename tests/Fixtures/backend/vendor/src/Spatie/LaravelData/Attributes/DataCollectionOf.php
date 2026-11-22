<?php

namespace Spatie\LaravelData\Attributes;

#[\Attribute(8)]
class DataCollectionOf
{
    public string $class;
    public function __construct(string $class) {}
}
