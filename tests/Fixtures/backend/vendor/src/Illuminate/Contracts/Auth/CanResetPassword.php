<?php

namespace Illuminate\Contracts\Auth;

interface CanResetPassword
{
    /** @return string */
    public function getEmailForPasswordReset();
    /** @return void */
    public function sendPasswordResetNotification($token);
}
