<?php

declare(strict_types=1);

namespace Notifications;

/**
 * ADDED CLASS — named exception for the registry's invariant: a requested
 * channel key must have been registered at boot.
 */
final class ChannelNotRegisteredException extends \RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("No channel registered for key '{$key}'.");
    }
}
