<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\CodeCommandments\Exceptions\ConfigurationException;

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
            throw ConfigurationException::fileNotFound($path);
        }

        // A Laravel app's commandments.php may use framework path helpers
        // (app_path(), base_path(), …). Standalone there is no bound
        // Application, so app_path() fatals with "Container::path() undefined".
        // Bind a minimal Application rooted at the project so those helpers
        // resolve before we require the config.
        self::ensureFrameworkPathHelpers();

        $config = require $path;

        if (!is_array($config)) {
            throw ConfigurationException::notAnArray($path);
        }

        return array_merge(self::defaults(), $config);
    }

    /**
     * Bind a minimal Laravel Application (rooted at the project) so a config
     * that calls app_path()/base_path() works under the standalone CLI. A
     * no-op for non-Laravel consumers (the foundation isn't installed) — their
     * configs don't use these helpers.
     */
    private static function ensureFrameworkPathHelpers(): void
    {
        $applicationClass = 'Illuminate\\Foundation\\Application';
        $containerClass = 'Illuminate\\Container\\Container';

        if (! class_exists($applicationClass) || ! class_exists($containerClass)) {
            return;
        }

        $current = $containerClass::getInstance();

        if ($current instanceof $applicationClass) {
            return;
        }

        // The Application constructor binds itself as the container instance,
        // wiring app_path()/base_path() to the project root.
        new $applicationClass(Environment::basePath());
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
