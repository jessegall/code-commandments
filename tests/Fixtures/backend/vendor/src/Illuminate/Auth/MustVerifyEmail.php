<?php

namespace Illuminate\Auth;

trait MustVerifyEmail
{
    /** @return bool */
    public function hasVerifiedEmail() {}
    /** @return bool */
    public function markEmailAsVerified() {}
    /** @return bool */
    public function markEmailAsUnverified() {}
    /** @return void */
    public function sendEmailVerificationNotification() {}
    /** @return string */
    public function getEmailForVerification() {}
}
