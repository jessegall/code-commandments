<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

use Illuminate\Support\ServiceProvider;
use JesseGall\CodeCommandments\Commands\AbsolveCommand;
use JesseGall\CodeCommandments\Commands\ReportCommand;
use JesseGall\CodeCommandments\Commands\ReportsCommand;
use JesseGall\CodeCommandments\Commands\ScaffoldCommand;
use JesseGall\CodeCommandments\Commands\InstallHooksCommand;
use JesseGall\CodeCommandments\Commands\InstallSyncHookCommand;
use JesseGall\CodeCommandments\Commands\JudgeCommand;
use JesseGall\CodeCommandments\Commands\MakeProphetCommand;
use JesseGall\CodeCommandments\Commands\RepentCommand;
use JesseGall\CodeCommandments\Commands\ScriptureCommand;
use JesseGall\CodeCommandments\Commands\SyncCommand;
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
            $manager = new ScrollManager(
                $app->make(ProphetRegistry::class),
                $app->make(FileScanner::class)
            );

            $manager->setFindingsCache(new \JesseGall\CodeCommandments\Support\Caching\FindingsCache(
                base_path('.commandments/cache/findings.json'),
                new \Illuminate\Filesystem\Filesystem(),
            ));

            return $manager;
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

            // Raw support-class stubs, for those who prefer the native
            // vendor:publish flow over `commandments:scaffold` (note: this does
            // NOT rewrite the namespace — the command does).
            $this->publishes([
                __DIR__ . '/../stubs/scaffold' => base_path('stubs/commandments/scaffold'),
            ], 'commandments-scaffold');

            $this->commands([
                JudgeCommand::class,
                AbsolveCommand::class,
                RepentCommand::class,
                ScriptureCommand::class,
                ScaffoldCommand::class,
                ReportCommand::class,
                ReportsCommand::class,
                MakeProphetCommand::class,
                InstallHooksCommand::class,
                InstallSyncHookCommand::class,
                SyncCommand::class,
            ]);
        }
    }

    protected function registerProphetsFromConfig(ProphetRegistry $registry): void
    {
        $scrolls = config('commandments.scrolls', []);
        $scaffold = config('commandments.scaffold', []);

        // When scaffold auto-refresh is on, the generated support classes are
        // regenerated automatically — judging them is pointless (and the
        // findings can't be fixed since they'd be overwritten), so exclude the
        // scaffold path from every scroll.
        $autoRefreshPath = ! empty($scaffold['auto_refresh']) && is_string($scaffold['path'] ?? null) && $scaffold['path'] !== ''
            ? $scaffold['path']
            : null;

        foreach ($scrolls as $scrollName => $scrollConfig) {
            if ($autoRefreshPath !== null) {
                $scrollConfig['exclude'] = [...($scrollConfig['exclude'] ?? []), $autoRefreshPath];
            }

            $prophets = $scrollConfig['prophets'] ?? [];

            $registry->registerMany($scrollName, $prophets);
            $registry->setScrollConfig($scrollName, $scrollConfig);
        }
    }
}
