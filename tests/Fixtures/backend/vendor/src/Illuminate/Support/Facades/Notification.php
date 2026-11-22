<?php

namespace Illuminate\Support\Facades;

class Notification extends \Illuminate\Support\Facades\Facade
{
    /** @return \Illuminate\Support\Testing\Fakes\NotificationFake */
    public static function fake() {}
    /** @return \Illuminate\Notifications\AnonymousNotifiable */
    public static function routes(array $channels) {}
    /** @return \Illuminate\Notifications\AnonymousNotifiable */
    public static function route($channel, $route) {}
    /** @return string */
    protected static function getFacadeAccessor() {}
}
