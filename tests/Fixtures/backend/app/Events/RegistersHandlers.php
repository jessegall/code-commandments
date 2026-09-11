<?php

namespace Shop\Events;

use Illuminate\Contracts\Events\Dispatcher;

interface RegistersHandlers
{
    public static function register(Dispatcher $events): void;
}
