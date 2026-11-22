<?php

namespace Illuminate\Foundation\Support\Providers;

class EventServiceProvider extends \Illuminate\Support\ServiceProvider
{
    protected $listen = array (
);
    protected $subscribe = array (
);
    protected $observers = array (
);
    protected static $shouldDiscoverEvents = true;
    protected static $eventDiscoveryPaths = NULL;
    /** @return void */
    public function register() {}
    /** @return void */
    public function boot() {}
    /** @return array */
    public function listens() {}
    /** @return array */
    public function getEvents() {}
    /** @return array */
    protected function discoveredEvents() {}
    /** @return bool */
    public function shouldDiscoverEvents() {}
    /** @return array */
    public function discoverEvents() {}
    /** @return iterable<int, */
    protected function discoverEventsWithin() {}
    /** @return void */
    public static function addEventDiscoveryPaths(\Traversable|array|string $paths) {}
    /** @return void */
    public static function setEventDiscoveryPaths(iterable $paths) {}
    /** @return string */
    protected function eventDiscoveryBasePath() {}
    /** @return void */
    public static function disableEventDiscovery() {}
    /** @return void */
    protected function configureEmailVerification() {}
}
