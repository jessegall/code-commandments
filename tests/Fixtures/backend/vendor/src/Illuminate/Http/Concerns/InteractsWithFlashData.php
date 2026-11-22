<?php

namespace Illuminate\Http\Concerns;

trait InteractsWithFlashData
{
    public function old($key = NULL, $default = NULL) {}
    public function flash() {}
    public function flashOnly($keys) {}
    public function flashExcept($keys) {}
    public function flush() {}
}
