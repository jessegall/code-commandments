<?php

namespace Shop\Notifications;

use JesseGall\CodeCommandments\Sins\Backend\NullableCallback;

use JesseGall\CodeCommandments\Testing\Sinful;

final class OrderNotifier
{
    #[Sinful(NullableCallback::class)]
    public function dispatch(string $message, ?callable $onSent = null): void
    {
        // ... send the message ...

        if ($onSent !== null) {
            $onSent($message);
        }
    }
}
