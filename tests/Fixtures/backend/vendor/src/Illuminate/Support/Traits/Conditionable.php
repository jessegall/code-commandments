<?php

namespace Illuminate\Support\Traits;

trait Conditionable
{
    public function when($value = NULL, ?callable $callback = NULL, ?callable $default = NULL) {}
    public function unless($value = NULL, ?callable $callback = NULL, ?callable $default = NULL) {}
}
