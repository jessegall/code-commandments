<?php

namespace Shop\Telemetry;

use JesseGall\CodeCommandments\Sins\Backend\MutableStaticState;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * A tally kept on the class itself. It reads before it writes, but it asks what the number IS
 * rather than whether it is there — so this is a global that changes, and every test that runs
 * after another starts from a number the previous one left behind.
 */
final class IssuedLabels
{
    private static int $issued = 0;

    private static int $voided = 0;

    #[Sinful(MutableStaticState::class)]
    public static function issue(int $count): void
    {
        static::$issued += $count;
    }

    #[Sinful(MutableStaticState::class)]
    public static function void(): void
    {
        static::$voided++;
    }

    public static function outstanding(): int
    {
        return static::$issued - static::$voided;
    }
}
