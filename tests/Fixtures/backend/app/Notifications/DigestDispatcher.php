<?php

namespace Shop\Notifications;

use JesseGall\CodeCommandments\Sins\Backend\ShortCircuitStatement;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Contracts\Mailer;

/**
 * Sends the weekly digest — and gates the send off the right side of a bare `&&`, so the
 * branch is written as an expression nothing reads. The righteous twin (`sendGuarded`)
 * spells the same decision as an `if`.
 */
final class DigestDispatcher
{
    public function __construct(private readonly Mailer $mailer) {}

    #[Sinful(ShortCircuitStatement::class)]
    public function send(string $address, bool $subscribed): void
    {
        $subscribed && $this->mailer->send($address, 'Your weekly digest', $this->digest());
    }

    #[Fixed(ShortCircuitStatement::class)]
    #[Righteous(ShortCircuitStatement::class)]
    public function sendGuarded(string $address, bool $subscribed): void
    {
        if (! $subscribed) {
            return;
        }

        $this->mailer->send($address, 'Your weekly digest', $this->digest());
    }

    private function digest(): string
    {
        return 'the weekly digest';
    }
}
