<?php

namespace Shop\Notifications;

use JesseGall\CodeCommandments\Detectors\Backend\CeremonyDocblockDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

final class SmsGateway
{
    public function __construct(
        private readonly string $sender = 'SHOP',
        private readonly bool $sandbox = true,
    ) {}

    /**
     * @param  string  $to
     * @param  string  $body
     * @param  bool  $flash
     */
    #[Sinful(CeremonyDocblockDetector::class)]
    public function send(string $to, string $body, bool $flash): bool
    {
        if ($this->sandbox) {
            return true;
        }

        return $to !== '' && $body !== '' && $flash === false;
    }
}
