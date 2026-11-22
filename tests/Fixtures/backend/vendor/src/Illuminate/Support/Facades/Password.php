<?php

namespace Illuminate\Support\Facades;

class Password extends \Illuminate\Support\Facades\Facade
{
    public const ResetLinkSent = 'passwords.sent';
    public const PasswordReset = 'passwords.reset';
    public const InvalidUser = 'passwords.user';
    public const InvalidToken = 'passwords.token';
    public const ResetThrottled = 'passwords.throttled';
    public const RESET_LINK_SENT = 'passwords.sent';
    public const PASSWORD_RESET = 'passwords.reset';
    public const INVALID_USER = 'passwords.user';
    public const INVALID_TOKEN = 'passwords.token';
    public const RESET_THROTTLED = 'passwords.throttled';
    protected static function getFacadeAccessor() {}
}
