<?php

namespace Illuminate\Auth;

trait Authenticatable
{
    protected $authPasswordName = 'password';
    protected $rememberTokenName = 'remember_token';
    public function getAuthIdentifierName() {}
    public function getAuthIdentifier() {}
    public function getAuthIdentifierForBroadcasting() {}
    public function getAuthPasswordName() {}
    public function getAuthPassword() {}
    public function getRememberToken() {}
    public function setRememberToken($value) {}
    public function getRememberTokenName() {}
}
