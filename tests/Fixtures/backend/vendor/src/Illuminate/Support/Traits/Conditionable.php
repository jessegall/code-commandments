<?php

namespace Illuminate\Support\Traits;

trait Conditionable
{
    /** @return $this|TWhenReturnType */
    public function when($value = NULL, ?callable $callback = NULL, ?callable $default = NULL) {}
    /** @return $this|TUnlessReturnType */
    public function unless($value = NULL, ?callable $callback = NULL, ?callable $default = NULL) {}
}
