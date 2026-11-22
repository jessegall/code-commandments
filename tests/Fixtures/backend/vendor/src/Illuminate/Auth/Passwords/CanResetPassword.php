<?php

namespace Illuminate\Auth\Passwords;

trait CanResetPassword
{
    /** @return string */
    public function getEmailForPasswordReset() {}
    /** @return void */
    public function sendPasswordResetNotification($token) {}
}
