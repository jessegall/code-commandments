<?php

namespace Illuminate\Support\Traits;

trait ReflectsClosures
{
    protected function firstClosureParameterType(\Closure $closure) {}
    protected function firstClosureParameterTypes(\Closure $closure) {}
    protected function closureParameterTypes(\Closure $closure) {}
    protected function closureReturnTypes(\Closure $closure) {}
}
