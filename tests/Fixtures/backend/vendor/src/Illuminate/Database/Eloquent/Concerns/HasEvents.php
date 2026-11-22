<?php

namespace Illuminate\Database\Eloquent\Concerns;

trait HasEvents
{
    protected $dispatchesEvents = array (
);
    protected $observables = array (
);
    public static function bootHasEvents() {}
    public static function resolveObserveAttributes() {}
    public static function observe($classes) {}
    protected function registerObserver($class) {}
    private function resolveObserverClassName($class) {}
    public function getObservableEvents() {}
    public function setObservableEvents(array $observables) {}
    public function addObservableEvents($observables) {}
    public function removeObservableEvents($observables) {}
    protected static function registerModelEvent($event, $callback) {}
    protected function fireModelEvent($event, $halt = true) {}
    protected function fireCustomModelEvent($event, $method) {}
    protected function filterModelEventResults($result) {}
    public static function retrieved($callback) {}
    public static function saving($callback) {}
    public static function saved($callback) {}
    public static function updating($callback) {}
    public static function updated($callback) {}
    public static function creating($callback) {}
    public static function created($callback) {}
    public static function replicating($callback) {}
    public static function deleting($callback) {}
    public static function deleted($callback) {}
    public static function flushEventListeners() {}
    public function dispatchesEvents() {}
    public static function getEventDispatcher() {}
    public static function setEventDispatcher(\Illuminate\Contracts\Events\Dispatcher $dispatcher) {}
    public static function unsetEventDispatcher() {}
    public static function withoutEvents(callable $callback) {}
}
