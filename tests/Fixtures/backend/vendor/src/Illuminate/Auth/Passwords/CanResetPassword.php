<?php

namespace Illuminate\Auth\Passwords;

trait CanResetPassword
{
    public function getEmailForPasswordReset() {}
    public function sendPasswordResetNotification($token) {}
}
