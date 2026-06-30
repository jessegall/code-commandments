<?php

namespace Shop\Orders;

use JesseGall\CodeCommandments\Sins\Backend\StringMatchMirrorsEnum;

use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Picks a badge from a raw status string whose cases mirror the OrderStatus enum —
 * dispatch on the enum, not the loose string.
 */
final class StatusBadge
{
    #[Sinful(StringMatchMirrorsEnum::class)]
    public function colour(string $status): string
    {
        return match ($status) {
            'pending' => 'grey',
            'paid' => 'green',
            'shipped' => 'blue',
            'cancelled' => 'red',
            default => 'black',
        };
    }

    public function sortDirection(string $direction): string
    {
        return match ($direction) {
            'asc' => '↑',
            'desc' => '↓',
            default => '',
        };
    }
}
