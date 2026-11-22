<?php

namespace Illuminate\Contracts\Events;

interface Dispatcher
{
    public function listen($events, $listener = NULL);
    public function hasListeners($eventName);
    public function subscribe($subscriber);
    public function until($event, $payload = array (
));
    public function dispatch($event, $payload = array (
), $halt = false);
    public function push($event, $payload = array (
));
    public function flush($event);
    public function forget($event);
    public function forgetPushed();
}
