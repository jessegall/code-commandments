<?php

namespace Illuminate\Auth;

trait Authenticatable
{
    protected $authPasswordName = 'password';
    protected $rememberTokenName = 'remember_token';
    /** @return string */
    public function getAuthIdentifierName() {}
    /** @return mixed */
    public function getAuthIdentifier() {}
    /** @return mixed */
    public function getAuthIdentifierForBroadcasting() {}
    /** @return string */
    public function getAuthPasswordName() {}
    /** @return string */
    public function getAuthPassword() {}
    /** @return string|null */
    public function getRememberToken() {}
    /** @return void */
    public function setRememberToken($value) {}
    /** @return string */
    public function getRememberTokenName() {}
}
