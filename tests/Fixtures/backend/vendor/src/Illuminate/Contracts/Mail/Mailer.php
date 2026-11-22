<?php

namespace Illuminate\Contracts\Mail;

interface Mailer
{
    public function to($users);
    public function bcc($users);
    public function raw($text, $callback);
    public function send($view, array $data = array (
), $callback = NULL);
    public function sendNow($mailable, array $data = array (
), $callback = NULL);
}
