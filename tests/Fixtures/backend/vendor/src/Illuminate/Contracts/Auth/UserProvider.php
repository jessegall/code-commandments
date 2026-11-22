<?php

namespace Illuminate\Contracts\Auth;

interface UserProvider
{
    /** @return \Illuminate\Contracts\Auth\Authenticatable|null */
    public function retrieveById($identifier);
    /** @return \Illuminate\Contracts\Auth\Authenticatable|null */
    public function retrieveByToken($identifier, $token);
    /** @return void */
    public function updateRememberToken(\Illuminate\Contracts\Auth\Authenticatable $user, $token);
    /** @return \Illuminate\Contracts\Auth\Authenticatable|null */
    public function retrieveByCredentials(array $credentials);
    /** @return bool */
    public function validateCredentials(\Illuminate\Contracts\Auth\Authenticatable $user, array $credentials);
    /** @return void */
    public function rehashPasswordIfRequired(\Illuminate\Contracts\Auth\Authenticatable $user, array $credentials, bool $force = false);
}
