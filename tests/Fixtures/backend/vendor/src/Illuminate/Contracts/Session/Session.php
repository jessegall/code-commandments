<?php

namespace Illuminate\Contracts\Session;

interface Session
{
    /** @return string */
    public function getName();
    /** @return void */
    public function setName($name);
    /** @return string */
    public function getId();
    /** @return void */
    public function setId($id);
    /** @return bool */
    public function start();
    /** @return void */
    public function save();
    /** @return array */
    public function all();
    /** @return bool */
    public function exists($key);
    /** @return bool */
    public function has($key);
    /** @return mixed */
    public function get($key, $default = NULL);
    /** @return mixed */
    public function pull($key, $default = NULL);
    /** @return void */
    public function put($key, $value = NULL);
    /** @return void */
    public function flash(string $key, $value = true);
    /** @return string */
    public function token();
    /** @return void */
    public function regenerateToken();
    /** @return mixed */
    public function remove($key);
    /** @return void */
    public function forget($keys);
    /** @return void */
    public function flush();
    /** @return bool */
    public function invalidate();
    /** @return bool */
    public function regenerate($destroy = false);
    /** @return bool */
    public function migrate($destroy = false);
    /** @return bool */
    public function isStarted();
    /** @return string|null */
    public function previousUrl();
    /** @return void */
    public function setPreviousUrl($url);
    /** @return \SessionHandlerInterface */
    public function getHandler();
    /** @return bool */
    public function handlerNeedsRequest();
    /** @return void */
    public function setRequestOnHandler($request);
}
