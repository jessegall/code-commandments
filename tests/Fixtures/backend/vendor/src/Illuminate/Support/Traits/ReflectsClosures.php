<?php

namespace Illuminate\Support\Traits;

trait ReflectsClosures
{
    /** @return string */
    protected function firstClosureParameterType(\Closure $closure) {}
    /** @return array */
    protected function firstClosureParameterTypes(\Closure $closure) {}
    /** @return array */
    protected function closureParameterTypes(\Closure $closure) {}
    /** @return list<class-string> */
    protected function closureReturnTypes(\Closure $closure) {}
}
