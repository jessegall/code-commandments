<?php

namespace Shop\Services;

use Illuminate\Support\Facades\Mail;
use JesseGall\CodeCommandments\Detectors\Backend\ConfigReadDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

final class NotificationService
{
    #[Sinful(ConfigReadDetector::class)]
    public function notify(string $email, string $type): void
    {
        $template = config('shop.templates.' . $type);

        Mail::raw($template, function ($message) use ($email) {
            $message->to($email);
        });
    }
}
