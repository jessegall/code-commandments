<?php

namespace App\Notifications;

interface Channel
{
    // no real contract — channels are matched by class name elsewhere
}

class EmailChannel implements Channel
{
    public function send($recipient, array $data)
    {
        \Illuminate\Support\Facades\Log::info('sending email', $data);
    }
}

class SmsChannel implements Channel
{
    public function send($recipient, array $data)
    {
        \Illuminate\Support\Facades\Log::info('sending sms', $data);
    }
}

class PushChannel implements Channel
{
    public function send($recipient, array $data)
    {
        \Illuminate\Support\Facades\Log::info('sending push', $data);
    }
}
