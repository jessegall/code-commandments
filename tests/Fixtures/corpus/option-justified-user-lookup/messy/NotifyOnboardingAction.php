<?php

namespace App\OptionCorpus\OptionJustifiedUserLookup\Messy;

/** Caller #3: threads the value into a follow-up step — the nullable forces a nested guard pyramid. */
final readonly class NotifyOnboardingAction
{
    public function __construct(
        private UserDirectory $directory,
        private NotificationGateway $gateway,
    ) {}

    public function notify(string $email): bool
    {
        $user = $this->directory->findByEmail($email);

        if ($user === null) {
            return false;
        }

        if (! $user->active) {
            return false;
        }

        return $this->gateway->sendWelcome($user->email);
    }
}
