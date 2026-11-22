<?php

namespace Illuminate\Database\Eloquent\Attributes;

#[\Attribute(1)]
class UsePolicy
{
    public string $class;
    public function __construct(string $class) {}
}
