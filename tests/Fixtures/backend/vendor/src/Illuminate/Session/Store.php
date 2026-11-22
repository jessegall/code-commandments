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
    /** @return bool */
    public function start() {}
    /** @return void */
    protected function loadSession() {}
    /** @return array */
    protected function readFromHandler() {}
    /** @return string */
    protected function prepareForUnserialize($data) {}
    /** @return void */
    protected function marshalErrorBag() {}
    /** @return void */
    public function save() {}
    /** @return void */
    protected function prepareErrorBagForSerialization() {}
    /** @return string */
    protected function prepareForStorage($data) {}
    /** @return void */
    public function ageFlashData() {}
    /** @return array */
    public function all() {}
    /** @return array */
    public function only(array $keys) {}
    /** @return array */
    public function except(array $keys) {}
    /** @return bool */
    public function exists($key) {}
    /** @return bool */
    public function missing($key) {}
    /** @return bool */
    public function has($key) {}
    /** @return bool */
    public function hasAny($key) {}
    /** @return mixed */
    public function get($key, $default = NULL) {}
    /** @return mixed */
    public function pull($key, $default = NULL) {}
    /** @return bool */
    public function hasOldInput($key = NULL) {}
    /** @return mixed */
    public function getOldInput($key = NULL, $default = NULL) {}
    /** @return void */
    public function replace(array $attributes) {}
    /** @return void */
    public function put($key, $value = NULL) {}
    /** @return mixed */
    public function remember($key, \Closure $callback) {}
    /** @return void */
    public function push($key, $value) {}
    /** @return mixed */
    public function increment($key, $amount = 1) {}
    /** @return int */
    public function decrement($key, $amount = 1) {}
    /** @return void */
    public function flash(\BackedEnum|\UnitEnum|string $key, $value = true) {}
    /** @return void */
    public function now($key, $value) {}
    /** @return void */
    public function reflash() {}
    /** @return void */
    public function keep($keys = NULL) {}
    /** @return void */
    protected function mergeNewFlashes(array $keys) {}
    /** @return void */
    protected function removeFromOldFlashData(array $keys) {}
    /** @return void */
    public function flashInput(array $value) {}
    /** @return \Illuminate\Contracts\Cache\Repository */
    public function cache() {}
    /** @return mixed */
    public function remove($key) {}
    /** @return void */
    public function forget($keys) {}
    /** @return void */
    public function flush() {}
    /** @return bool */
    public function invalidate() {}
    /** @return bool */
    public function regenerate($destroy = false) {}
    /** @return bool */
    public function migrate($destroy = false) {}
    /** @return bool */
    public function isStarted() {}
    /** @return string */
    public function getName() {}
    /** @return void */
    public function setName($name) {}
    /** @return string */
    public function id() {}
    /** @return string */
    public function getId() {}
    /** @return void */
    public function setId($id) {}
    /** @return bool */
    public function isValidId($id) {}
    /** @return string */
    protected function generateSessionId() {}
    /** @return void */
    public function setExists($value) {}
    /** @return string */
    public function token() {}
    /** @return void */
    public function regenerateToken() {}
    /** @return bool */
    public function hasPreviousUri() {}
    /** @return \Illuminate\Support\Uri */
    public function previousUri() {}
    /** @return string|null */
    public function previousUrl() {}
    /** @return void */
    public function setPreviousUrl($url) {}
    /** @return string|null */
    public function previousRoute() {}
    /** @return void */
    public function setPreviousRoute($route) {}
    /** @return void */
    public function passwordConfirmed() {}
    /** @return \SessionHandlerInterface */
    public function getHandler() {}
    /** @return \SessionHandlerInterface */
    public function setHandler(\SessionHandlerInterface $handler) {}
    /** @return bool */
    public function handlerNeedsRequest() {}
    /** @return void */
    public function setRequestOnHandler($request) {}
}
