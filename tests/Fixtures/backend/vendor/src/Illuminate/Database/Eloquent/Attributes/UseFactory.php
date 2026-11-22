<?php

namespace Illuminate\Database\Eloquent\Attributes;

#[\Attribute(1)]
class UseFactory
{
    public string $factoryClass;
    public function __construct(string $factoryClass) {}
}
