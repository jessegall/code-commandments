<?php

declare(strict_types=1);

namespace Notifications;

enum Severity: string
{
    case Low      = 'low';
    case Medium   = 'medium';
    case High     = 'high';
    case Critical = 'critical';

    /**
     * SMELL: closed-set match with `default => null`. Every severity escalates;
     * `Critical` was added later and never wired, so it silently falls to null.
     */
    public function escalationMinutes(): ?int
    {
        return match ($this) {
            self::Low    => 1440,
            self::Medium => 240,
            self::High   => 30,
            default      => null,
        };
    }

    public function channelKey(): string
    {
        return match ($this) {
            self::Low, self::Medium => 'email',
            self::High, self::Critical => 'sms',
        };
    }
}
