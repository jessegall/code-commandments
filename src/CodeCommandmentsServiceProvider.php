<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

use Illuminate\Support\ServiceProvider;
use JesseGall\CodeCommandments\Commands\InstallHooksCommand;
use JesseGall\CodeCommandments\Commands\JudgeCommand;
use JesseGall\CodeCommandments\Commands\MakeProphetCommand;
use JesseGall\CodeCommandments\Commands\RepentCommand;
use JesseGall\CodeCommandments\Commands\ScriptureCommand;
use JesseGall\CodeCommandments\Contracts\ConfessionTracker as ConfessionTrackerContract;
use JesseGall\CodeCommandments\Contracts\FileScanner;
use JesseGall\CodeCommandments\Scanners\GenericFileScanner;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\ScrollManager;
use JesseGall\CodeCommandments\Tracking\JsonConfessionTracker;
use Illuminate\Filesystem\Filesystem;

class CodeCommandmentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Environment::setBasePath(base_path());

        $this->mergeConfigFrom(
            __DIR__ . '/../config/commandments.php',
            'commandments'
        );

        $this->app->singleton(ProphetRegistry::class, function ($app) {
            $registry = new ProphetRegistry();

            $this->registerProphetsFromConfig($registry);

            return $registry;
        });

        $this->app->singleton(FileScanner::class, GenericFileScanner::class);

        $this->app->singleton(ConfessionTrackerContract::class, function ($app) {
            $tabletPath = config('commandments.confession.tablet_path', storage_path('commandments/confessions.json'));

            return new JsonConfessionTracker($tabletPath, $app->make(Filesystem::class));
        });

        $this->app->singleton(ScrollManager::class, function ($app) {
            return new ScrollManager(
                $app->make(ProphetRegistry::class),
                $app->make(FileScanner::class)
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/commandments.php' => config_path('commandments.php'),
            ], 'commandments-config');

            $this->publishes([
                __DIR__ . '/../stubs' => base_path('stubs/commandments'),
            ], 'commandments-stubs');

            $this->commands([
                JudgeCommand::class,
                RepentCommand::class,
                ScriptureCommand::class,
                MakeProphetCommand::class,
                InstallHooksCommand::class,
            ]);
        }
    }

    protected function registerProphetsFromConfig(ProphetRegistry $registry): void
    {
        $scrolls = config('commandments.scrolls', []);

        foreach ($scrolls as $scrollName => $scrollConfig) {
            $prophets = $scrollConfig['prophets'] ?? [];

            $registry->registerMany($scrollName, $prophets);
            $registry->setScrollConfig($scrollName, $scrollConfig);
        }
    }
}
