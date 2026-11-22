<?php

namespace Illuminate\Contracts\Config;

interface Repository
{
    /** @return bool */
    public function has($key);
    /** @return mixed */
    public function get($key, $default = NULL);
    /** @return array */
    public function all();
    /** @return void */
    public function set($key, $value = NULL);
    /** @return void */
    public function prepend($key, $value);
    /** @return void */
    public function push($key, $value);
}
