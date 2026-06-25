<?php

namespace Shop\Services;

use Illuminate\Support\Facades\Mail;

final class NotificationService
{
    public function notify(string $email, string $type): void
    {
        $template = config('shop.templates.' . $type);

        Mail::raw($template, function ($message) use ($email) {
            $message->to($email);
        });
    }
}
