<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use Composer\Autoload\ClassLoader;

/**
 * Resolves the active Composer ClassLoader from the registered autoloaders,
 * memoised for the process.
 */
final class ComposerLoader
{
    private static ?ClassLoader $loader = null;

    private static bool $resolved = false;

    public static function resolve(): ?ClassLoader
    {
        if (self::$resolved) {
            return self::$loader;
        }

        self::$resolved = true;

        foreach (spl_autoload_functions() ?: [] as $autoload) {
            if (is_array($autoload) && isset($autoload[0]) && $autoload[0] instanceof ClassLoader) {
                self::$loader = $autoload[0];

                return self::$loader;
            }
        }

        return null;
    }
}
