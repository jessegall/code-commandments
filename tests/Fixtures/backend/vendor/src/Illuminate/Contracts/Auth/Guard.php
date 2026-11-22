<?php

namespace Illuminate\Contracts\Auth;

interface Guard
{
    /** @return bool */
    public function check();
    /** @return bool */
    public function guest();
    /** @return \Illuminate\Contracts\Auth\Authenticatable|null */
    public function user();
    /** @return int|string|null */
    public function id();
    /** @return bool */
    public function validate(array $credentials = array (
));
    /** @return bool */
    public function hasUser();
    /** @return $this */
    public function setUser(\Illuminate\Contracts\Auth\Authenticatable $user);
}
