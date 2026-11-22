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
    public function register() {}
    public function boot() {}
    public function listens() {}
    public function getEvents() {}
    protected function discoveredEvents() {}
    public function shouldDiscoverEvents() {}
    public function discoverEvents() {}
    protected function discoverEventsWithin() {}
    public static function addEventDiscoveryPaths(\Traversable|array|string $paths) {}
    public static function setEventDiscoveryPaths(iterable $paths) {}
    protected function eventDiscoveryBasePath() {}
    public static function disableEventDiscovery() {}
    protected function configureEmailVerification() {}
}
