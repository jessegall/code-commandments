<?php

namespace Shop\Tills;

use JesseGall\CodeCommandments\Sins\Backend\MatchDefaultReturnsNull;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Where a till stands through the day, from opening to being locked for the night.
 */
enum TillState: string
{
    case Open = 'open';
    case Counting = 'counting';
    case Closed = 'closed';
    case Locked = 'locked';

    #[Sinful(MatchDefaultReturnsNull::class)]
    public function lampColour(): ?string
    {
        return match ($this) {
            self::Open => 'green',
            self::Counting => 'amber',
            default => null,
        };
    }
}
