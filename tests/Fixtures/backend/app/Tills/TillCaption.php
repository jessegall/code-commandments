<?php

namespace Shop\Tills;

/**
 * The caption a receipt prints for a till event, worded by the event's kind.
 */
final class TillCaption
{
    public static function for(string $event, int $till): string
    {
        return "till {$till} {$event}";
    }
}
