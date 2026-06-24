<?php

namespace App\OptionCorpus\OptionJustifiedUserLookup\Messy;

/** Sends transactional onboarding mail. */
final readonly class NotificationGateway
{
    public function sendWelcome(string $email): bool
    {
        return $email !== '';
    }
}
