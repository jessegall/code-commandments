<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\ConfigRead;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * The cutoff hour the dispatch config names, read once per process and handed out from there.
 */
#[Righteous(ConfigRead::class)]
final class DispatchZones
{
    private static ?int $cutoffHour = null;

    public static function cutoffHour(): int
    {
        if (self::$cutoffHour === null) {
            self::$cutoffHour = (int) config('dispatch.cutoff_hour');
        }

        return self::$cutoffHour;
    }
}
