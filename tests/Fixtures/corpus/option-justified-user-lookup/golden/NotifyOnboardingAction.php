<?php

namespace App\OptionCorpus\OptionJustifiedUserLookup\Golden;

/** Caller #3: feeds the value into a follow-up step only when present and active. */
final readonly class NotifyOnboardingAction
{
    public function __construct(
        private UserDirectory $directory,
        private NotificationGateway $gateway,
    ) {}

    public function notify(string $email): bool
    {
        return $this->directory
            ->findByEmail($email)
            ->filter(fn (User $u) => $u->active)
            ->map(fn (User $u) => $this->gateway->sendWelcome($u->email))
            ->getOrElse(false);
    }
}
