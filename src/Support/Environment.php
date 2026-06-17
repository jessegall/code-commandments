<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\PhpTypes\T_String;

/**
 * Static holder for the base path.
 * Set by the Laravel ServiceProvider or the standalone CLI.
 */
class Environment
{
    private static ?string $basePath = null;

    public static function setBasePath(string $path): void
    {
        self::$basePath = rtrim($path, '/');
    }

    public static function basePath(string $path = T_String::EMPTY): string
    {
        $base = self::$basePath ?? getcwd();

        return T_String::isNotEmpty($path) ? $base . '/' . $path : $base;
    }
}
