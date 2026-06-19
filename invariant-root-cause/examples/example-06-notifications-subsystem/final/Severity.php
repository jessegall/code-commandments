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
     * Total: every case handled, no `default`. A new severity is now a
     * compile-time match error instead of a silent null.
     */
    public function escalationMinutes(): int
    {
        return match ($this) {
            self::Low      => 1440,
            self::Medium   => 240,
            self::High     => 30,
            self::Critical => 5,
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
