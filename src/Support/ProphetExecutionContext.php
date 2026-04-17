<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * Tracks which prophet is running against which file, so a shutdown
 * handler can point at the culprit if the PHP engine hits a
 * non-catchable fatal (e.g. compile-time errors triggered by
 * autoloading a broken consumer class).
 */
final class ProphetExecutionContext
{
    private static ?string $currentProphet = null;

    private static ?string $currentFile = null;

    private static bool $shutdownRegistered = false;

    public static function register(): void
    {
        if (self::$shutdownRegistered) {
            return;
        }

        self::$shutdownRegistered = true;

        register_shutdown_function(static function (): void {
            $err = error_get_last();

            if ($err === null) {
                return;
            }

            $fatalTypes = [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR, E_PARSE];

            if (! in_array($err['type'], $fatalTypes, true)) {
                return;
            }

            if (self::$currentProphet === null || self::$currentFile === null) {
                return;
            }

            fwrite(STDERR, "\n");
            fwrite(STDERR, "[commandments] PHP fatal error while running prophet: " . self::$currentProphet . "\n");
            fwrite(STDERR, "[commandments] While judging file: " . self::$currentFile . "\n");
            fwrite(STDERR, "[commandments] The prophet likely autoloaded a consumer class that has a fatal-level issue.\n");
            fwrite(STDERR, "[commandments] Fix the consumer class, or exclude it from this prophet.\n");
        });
    }

    public static function enter(string $prophet, string $file): void
    {
        self::$currentProphet = $prophet;
        self::$currentFile = $file;
    }

    public static function leave(): void
    {
        self::$currentProphet = null;
        self::$currentFile = null;
    }
}
