<?php

namespace Illuminate\Console\Concerns;

trait InteractsWithSignals
{
    protected $signals = NULL;
    /** @return void */
    public function trap($signals, $callback) {}
    /** @return void */
    public function untrap() {}
}
