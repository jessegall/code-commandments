<?php

namespace Shop\Diagnostics;

use JesseGall\CodeCommandments\Sins\Backend\MutableStaticState;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * A trace buffer parked on the class, so every part of the system writes into one shared list
 * that nothing in a signature mentions. What a reader sees depends entirely on what ran first,
 * and a test that forgets to clear it inherits the previous one's history.
 */
final class TraceBuffer
{
    private const int KEEP = 200;

    /**
     * @var array<int, string>
     */
    private static array $entries = [];

    private static string $sink = 'memory';

    #[Sinful(MutableStaticState::class)]
    public static function record(string $at, string $message): void
    {
        self::$entries[] = "[{$at}] {$message}";

        if (count(self::$entries) > self::KEEP) {
            self::$entries = array_slice(self::$entries, -self::KEEP);
        }
    }

    #[Sinful(MutableStaticState::class)]
    public static function sinkTo(string $sink): void
    {
        self::$sink = $sink;
    }

    /**
     * @return array<int, string>
     */
    public static function lines(): array
    {
        return self::$entries;
    }

    public static function sink(): string
    {
        return self::$sink;
    }
}
