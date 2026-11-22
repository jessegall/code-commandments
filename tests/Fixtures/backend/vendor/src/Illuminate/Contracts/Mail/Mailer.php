<?php

namespace Illuminate\Contracts\Mail;

interface Mailer
{
    /** @return \Illuminate\Mail\PendingMail */
    public function to($users);
    /** @return \Illuminate\Mail\PendingMail */
    public function bcc($users);
    /** @return \Illuminate\Mail\SentMessage|null */
    public function raw($text, $callback);
    /** @return \Illuminate\Mail\SentMessage|null */
    public function send($view, array $data = array (
), $callback = NULL);
    /** @return \Illuminate\Mail\SentMessage|null */
    public function sendNow($mailable, array $data = array (
), $callback = NULL);
}
