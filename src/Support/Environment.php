<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

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

    public static function basePath(string $path = ''): string
    {
        $base = self::$basePath ?? getcwd();

        return $path !== '' ? $base . '/' . $path : $base;
    }
}
