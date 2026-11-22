<?php

namespace Illuminate\Session;

class Store implements \Illuminate\Contracts\Session\Session
{
    use \Illuminate\Support\Traits\Macroable;
    protected const SESSION_ID_LENGTH = 40;
    protected $id = NULL;
    protected $name = NULL;
    protected $attributes = array (
);
    protected $handler = NULL;
    protected $serialization = 'php';
    protected $started = false;
    public function __construct($name, \SessionHandlerInterface $handler, $id = NULL, $serialization = 'php') {}
    public function start() {}
    protected function loadSession() {}
    protected function readFromHandler() {}
    protected function prepareForUnserialize($data) {}
    protected function marshalErrorBag() {}
    public function save() {}
    protected function prepareErrorBagForSerialization() {}
    protected function prepareForStorage($data) {}
    public function ageFlashData() {}
    public function all() {}
    public function only(array $keys) {}
    public function except(array $keys) {}
    public function exists($key) {}
    public function missing($key) {}
    public function has($key) {}
    public function hasAny($key) {}
    public function get($key, $default = NULL) {}
    public function pull($key, $default = NULL) {}
    public function hasOldInput($key = NULL) {}
    public function getOldInput($key = NULL, $default = NULL) {}
    public function replace(array $attributes) {}
    public function put($key, $value = NULL) {}
    public function remember($key, \Closure $callback) {}
    public function push($key, $value) {}
    public function increment($key, $amount = 1) {}
    public function decrement($key, $amount = 1) {}
    public function flash(\BackedEnum|\UnitEnum|string $key, $value = true) {}
    public function now($key, $value) {}
    public function reflash() {}
    public function keep($keys = NULL) {}
    protected function mergeNewFlashes(array $keys) {}
    protected function removeFromOldFlashData(array $keys) {}
    public function flashInput(array $value) {}
    public function cache() {}
    public function remove($key) {}
    public function forget($keys) {}
    public function flush() {}
    public function invalidate() {}
    public function regenerate($destroy = false) {}
    public function migrate($destroy = false) {}
    public function isStarted() {}
    public function getName() {}
    public function setName($name) {}
    public function id() {}
    public function getId() {}
    public function setId($id) {}
    public function isValidId($id) {}
    protected function generateSessionId() {}
    public function setExists($value) {}
    public function token() {}
    public function regenerateToken() {}
    public function hasPreviousUri() {}
    public function previousUri() {}
    public function previousUrl() {}
    public function setPreviousUrl($url) {}
    public function previousRoute() {}
    public function setPreviousRoute($route) {}
    public function passwordConfirmed() {}
    public function getHandler() {}
    public function setHandler(\SessionHandlerInterface $handler) {}
    public function handlerNeedsRequest() {}
    public function setRequestOnHandler($request) {}
}
