<?php

namespace Illuminate\Http\Concerns;

trait InteractsWithFlashData
{
    /** @return string|array|null */
    public function old($key = NULL, $default = NULL) {}
    /** @return void */
    public function flash() {}
    /** @return void */
    public function flashOnly($keys) {}
    /** @return void */
    public function flashExcept($keys) {}
    /** @return void */
    public function flush() {}
}
