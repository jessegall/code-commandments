<?php

namespace Illuminate\Contracts\Events;

interface Dispatcher
{
    /** @return void */
    public function listen($events, $listener = NULL);
    /** @return bool */
    public function hasListeners($eventName);
    /** @return void */
    public function subscribe($subscriber);
    /** @return mixed */
    public function until($event, $payload = array (
));
    /** @return array|null */
    public function dispatch($event, $payload = array (
), $halt = false);
    /** @return void */
    public function push($event, $payload = array (
));
    /** @return void */
    public function flush($event);
    /** @return void */
    public function forget($event);
    /** @return void */
    public function forgetPushed();
}
