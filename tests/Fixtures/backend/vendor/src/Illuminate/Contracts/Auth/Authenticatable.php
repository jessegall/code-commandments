<?php

namespace Illuminate\Contracts\Auth;

interface Authenticatable
{
    /** @return string */
    public function getAuthIdentifierName();
    /** @return mixed */
    public function getAuthIdentifier();
    /** @return string */
    public function getAuthPasswordName();
    /** @return string */
    public function getAuthPassword();
    /** @return string */
    public function getRememberToken();
    /** @return void */
    public function setRememberToken($value);
    /** @return string */
    public function getRememberTokenName();
}
