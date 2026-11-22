<?php

namespace Illuminate\Support\Traits;

trait Dumpable
{
    /** @return never */
    public function dd(...$args) {}
    /** @return $this */
    public function dump(...$args) {}
}
