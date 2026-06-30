<?php

namespace Shop\Notifications;

use JesseGall\CodeCommandments\Detectors\Backend\ContainerReachDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Reaches into the container for its gateway mid-method instead of declaring the
 * dependency in the constructor.
 */
final class SmsDispatcher
{
    #[Sinful(ContainerReachDetector::class)]
    public function send(string $to, string $body): void
    {
        $gateway = app(\Shop\Contracts\SmsGateway::class);

        $gateway->deliver($to, $body);
    }
}
