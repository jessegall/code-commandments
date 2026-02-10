<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use Illuminate\Filesystem\Filesystem;
use JesseGall\CodeCommandments\Scanners\GenericFileScanner;
use JesseGall\CodeCommandments\Support\ConfigLoader;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\ScrollManager;
use JesseGall\CodeCommandments\Tracking\JsonConfessionTracker;

/**
 * Shared bootstrap logic for standalone console commands.
 */
trait BootsStandalone
{
    protected function bootEnvironment(?string $configPath): array
    {
        $basePath = getcwd();
        Environment::setBasePath($basePath);

        $resolvedPath = ConfigLoader::resolve($configPath, $basePath);

        if ($resolvedPath === null) {
            throw new \RuntimeException(
                'No configuration file found. Create a commandments.php in your project root or pass --config=path.'
            );
        }

        $config = ConfigLoader::load($resolvedPath);

        $registry = new ProphetRegistry();

        foreach ($config['scrolls'] ?? [] as $scrollName => $scrollConfig) {
            $prophets = $scrollConfig['prophets'] ?? [];
            $registry->registerMany($scrollName, $prophets);
            $registry->setScrollConfig($scrollName, $scrollConfig);
        }

        $scanner = new GenericFileScanner();
        $manager = new ScrollManager($registry, $scanner);

        $tabletPath = $config['confession']['tablet_path']
            ?? Environment::basePath('.commandments/confessions.json');
        $tracker = new JsonConfessionTracker($tabletPath, new Filesystem());

        return [$registry, $manager, $tracker];
    }
}
