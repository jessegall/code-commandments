<?php

namespace Illuminate\Contracts\Auth;

interface UserProvider
{
    public function retrieveById($identifier);
    public function retrieveByToken($identifier, $token);
    public function updateRememberToken(\Illuminate\Contracts\Auth\Authenticatable $user, $token);
    public function retrieveByCredentials(array $credentials);
    public function validateCredentials(\Illuminate\Contracts\Auth\Authenticatable $user, array $credentials);
    public function rehashPasswordIfRequired(\Illuminate\Contracts\Auth\Authenticatable $user, array $credentials, bool $force = false);
}
