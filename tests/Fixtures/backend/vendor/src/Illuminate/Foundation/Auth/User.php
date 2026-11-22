<?php

namespace Illuminate\Foundation\Auth;

class User extends \Illuminate\Database\Eloquent\Model implements \Illuminate\Contracts\Auth\Authenticatable, \Illuminate\Contracts\Auth\Access\Authorizable, \Illuminate\Contracts\Auth\CanResetPassword
{
    use \Illuminate\Auth\Authenticatable, \Illuminate\Foundation\Auth\Access\Authorizable, \Illuminate\Auth\Passwords\CanResetPassword, \Illuminate\Auth\MustVerifyEmail;
}
