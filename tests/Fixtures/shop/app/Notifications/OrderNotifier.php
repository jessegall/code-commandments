<?php

namespace Shop\Notifications;

use JesseGall\CodeCommandments\Detectors\Backend\NullableCallbackDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

final class OrderNotifier
{
    #[Sinful(NullableCallbackDetector::class)]
    public function dispatch(string $message, ?callable $onSent = null): void
    {
        // ... send the message ...

        if ($onSent !== null) {
            $onSent($message);
        }
    }
}
