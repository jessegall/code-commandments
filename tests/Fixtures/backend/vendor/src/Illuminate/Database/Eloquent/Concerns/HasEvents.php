<?php

namespace Illuminate\Database\Eloquent\Concerns;

trait HasEvents
{
    protected $dispatchesEvents = array (
);
    protected $observables = array (
);
    /** @return void */
    public static function bootHasEvents() {}
    /** @return array */
    public static function resolveObserveAttributes() {}
    /** @return void */
    public static function observe($classes) {}
    /** @return void */
    protected function registerObserver($class) {}
    /** @return class-string */
    private function resolveObserverClassName($class) {}
    /** @return string[] */
    public function getObservableEvents() {}
    /** @return $this */
    public function setObservableEvents(array $observables) {}
    /** @return void */
    public function addObservableEvents($observables) {}
    /** @return void */
    public function removeObservableEvents($observables) {}
    /** @return void */
    protected static function registerModelEvent($event, $callback) {}
    /** @return mixed */
    protected function fireModelEvent($event, $halt = true) {}
    /** @return array|null|void */
    protected function fireCustomModelEvent($event, $method) {}
    /** @return mixed */
    protected function filterModelEventResults($result) {}
    /** @return void */
    public static function retrieved($callback) {}
    /** @return void */
    public static function saving($callback) {}
    /** @return void */
    public static function saved($callback) {}
    /** @return void */
    public static function updating($callback) {}
    /** @return void */
    public static function updated($callback) {}
    /** @return void */
    public static function creating($callback) {}
    /** @return void */
    public static function created($callback) {}
    /** @return void */
    public static function replicating($callback) {}
    /** @return void */
    public static function deleting($callback) {}
    /** @return void */
    public static function deleted($callback) {}
    /** @return void */
    public static function flushEventListeners() {}
    /** @return array */
    public function dispatchesEvents() {}
    /** @return \Illuminate\Contracts\Events\Dispatcher|null */
    public static function getEventDispatcher() {}
    /** @return void */
    public static function setEventDispatcher(\Illuminate\Contracts\Events\Dispatcher $dispatcher) {}
    /** @return void */
    public static function unsetEventDispatcher() {}
    /** @return mixed */
    public static function withoutEvents(callable $callback) {}
}
