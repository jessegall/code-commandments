<?php

namespace Illuminate\Console\Concerns;

trait InteractsWithSignals
{
    protected $signals = NULL;
    public function trap($signals, $callback) {}
    public function untrap() {}
}
