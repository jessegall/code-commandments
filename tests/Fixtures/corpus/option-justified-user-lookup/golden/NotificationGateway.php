<?php

namespace App\OptionCorpus\OptionJustifiedUserLookup\Golden;

/** Sends transactional onboarding mail. */
final readonly class NotificationGateway
{
    public function sendWelcome(string $email): bool
    {
        return $email !== '';
    }
}
