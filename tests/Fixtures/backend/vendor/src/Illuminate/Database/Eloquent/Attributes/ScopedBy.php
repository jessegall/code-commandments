<?php

namespace Illuminate\Database\Eloquent\Attributes;

#[\Attribute(129)]
class ScopedBy
{
    public array|string $classes;
    public function __construct(array|string $classes) {}
}
