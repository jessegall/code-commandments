<?php

namespace Shop\Notifications;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\ContainerReach;

use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Reaches into the container for its gateway mid-method instead of declaring the
 * dependency in the constructor.
 */
final class SmsDispatcher
{
    #[Sinful(ContainerReach::class)]
    public function send(string $to, string $body): void
    {
        $gateway = app(\Shop\Contracts\SmsGateway::class);

        $gateway->deliver($to, $body);
    }
}
