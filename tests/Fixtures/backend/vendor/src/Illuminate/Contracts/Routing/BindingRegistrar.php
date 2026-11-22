<?php

namespace Illuminate\Contracts\Routing;

interface BindingRegistrar
{
    /** @return void */
    public function bind($key, $binder);
    /** @return \Closure */
    public function getBindingCallback($key);
}
