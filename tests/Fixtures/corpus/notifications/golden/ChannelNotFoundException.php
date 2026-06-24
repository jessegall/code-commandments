<?php

declare(strict_types=1);

namespace App\Notifications;

use RuntimeException;

/**
 * Thrown when a channel is requested by a key that was never registered.
 */
final class ChannelNotFoundException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("No notification channel registered for `{$key}`.");
    }
}
