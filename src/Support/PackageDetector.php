<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * Detects whether specific packages are installed in the current project.
 */
class PackageDetector
{
    private static ?bool $hasSpatieData = null;

    private static ?bool $hasWayfinder = null;

    /**
     * Check if Spatie Laravel Data package is installed.
     */
    public static function hasSpatieData(): bool
    {
        if (self::$hasSpatieData === null) {
            self::$hasSpatieData = class_exists('Spatie\\LaravelData\\Data');
        }

        return self::$hasSpatieData;
    }

    /**
     * Check if Wayfinder package is installed.
     */
    public static function hasWayfinder(): bool
    {
        if (self::$hasWayfinder === null) {
            self::$hasWayfinder = is_dir(base_path('resources/js/actions'));
        }

        return self::$hasWayfinder;
    }

    /**
     * Clear the detection cache (useful for testing).
     */
    public static function clearCache(): void
    {
        self::$hasSpatieData = null;
        self::$hasWayfinder = null;
    }
}
