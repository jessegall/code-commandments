<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * Loads configuration from a PHP file for standalone CLI usage.
 */
class ConfigLoader
{
    /**
     * Load configuration from a file path.
     *
     * @return array<string, mixed>
     */
    public static function load(string $path): array
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Config file not found: {$path}");
        }

        $config = require $path;

        if (!is_array($config)) {
            throw new \RuntimeException("Config file must return an array: {$path}");
        }

        return array_merge(self::defaults(), $config);
    }

    /**
     * Resolve the config file path.
     *
     * Checks the given path first, then falls back to commandments.php in the base path.
     */
    public static function resolve(?string $configPath, string $basePath): ?string
    {
        if ($configPath !== null) {
            return realpath($configPath) ?: $configPath;
        }

        $default = $basePath . '/commandments.php';

        return file_exists($default) ? $default : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaults(): array
    {
        return [
            'scrolls' => [],
            'confession' => [
                'tablet_path' => Environment::basePath('.commandments/confessions.json'),
            ],
        ];
    }
}
