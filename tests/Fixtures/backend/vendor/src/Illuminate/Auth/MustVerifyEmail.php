<?php

namespace Illuminate\Auth;

trait MustVerifyEmail
{
    public function hasVerifiedEmail() {}
    public function markEmailAsVerified() {}
    public function markEmailAsUnverified() {}
    public function sendEmailVerificationNotification() {}
    public function getEmailForVerification() {}
}
