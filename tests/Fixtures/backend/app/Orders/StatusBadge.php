<?php

namespace Shop\Orders;

use JesseGall\CodeCommandments\Sins\Backend\StringMatchMirrorsEnum;

use JesseGall\CodeCommandments\Testing\Righteous;
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

    /**
     * A pluralisation over a CARDINAL COUNT — none, exactly one, and every other
     * number. Its arms are 0 and 1, the same two ordinals PickWave happens to back
     * its cases with, but a number is a quantity and not a name: the domain of
     * $many is the natural numbers, and there is no closed set here to seal.
     */
    #[Righteous(StringMatchMirrorsEnum::class)]
    public function counted(int $many, string $one, string $more): string
    {
        return match ($many) {
            0 => 'None yet',
            1 => '1 '.$one,
            default => $many.' '.$more,
        };
    }
}
