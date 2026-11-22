<?php

namespace Illuminate\Contracts\Config;

interface Repository
{
    public function has($key);
    public function get($key, $default = NULL);
    public function all();
    public function set($key, $value = NULL);
    public function prepend($key, $value);
    public function push($key, $value);
}
