<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

use Illuminate\Support\ServiceProvider;
use JesseGall\CodeCommandments\Commands\AbsolveCommand;
use JesseGall\CodeCommandments\Commands\ReportCommand;
use JesseGall\CodeCommandments\Commands\ReportsCommand;
use JesseGall\CodeCommandments\Commands\ScaffoldCommand;
use JesseGall\CodeCommandments\Commands\InstallHooksCommand;
use JesseGall\CodeCommandments\Commands\InstallSkillsCommand;
use JesseGall\CodeCommandments\Commands\SkillsCommand;
use JesseGall\CodeCommandments\Commands\InstallSyncHookCommand;
use JesseGall\CodeCommandments\Commands\JudgeCommand;
use JesseGall\CodeCommandments\Commands\MakeProphetCommand;
use JesseGall\CodeCommandments\Commands\ProfileCommand;
use JesseGall\CodeCommandments\Commands\RepentCommand;
use JesseGall\CodeCommandments\Commands\ScriptureCommand;
use JesseGall\CodeCommandments\Commands\NextCommand;
use JesseGall\CodeCommandments\Commands\PilgrimageCommand;
use JesseGall\CodeCommandments\Commands\SyncCommand;
use JesseGall\CodeCommandments\Commands\UpdateCommand;
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

            // Raw skill stubs, for those who prefer the native vendor:publish
            // flow over `commandments:install-skills` (note: this does NOT
            // rewrite the namespace nor land under .claude/ — the command does).
            $this->publishes([
                __DIR__ . '/../stubs/skills' => base_path('stubs/commandments/skills'),
            ], 'commandments-skills');

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
                InstallSkillsCommand::class,
                SkillsCommand::class,
                InstallSyncHookCommand::class,
                SyncCommand::class,
                UpdateCommand::class,
                PilgrimageCommand::class,
                NextCommand::class,
                ProfileCommand::class,
            ]);

            $this->ensureComposerLifecycleScripts();
        }
    }

    /**
     * First-load self-wiring: the very first time the package boots in a console
     * context, register `commandments update` on the consumer's composer
     * post-update / post-install scripts so every future install/update stays in
     * sync — no composer-plugin, no `allow-plugins` prompt. Marker-gated so it runs
     * once and never touches composer.json again on its own.
     */
    private function ensureComposerLifecycleScripts(): void
    {
        $basePath = $this->app->basePath();
        $marker = $basePath . '/.commandments/.composer-wired';

        if (is_file($marker)) {
            return;
        }

        // Never wire our OWN repository — only a consumer that depends on us.
        $composerJson = $basePath . '/composer.json';

        if (is_file($composerJson)) {
            $self = json_decode((string) file_get_contents($composerJson), true);

            if (is_array($self) && ($self['name'] ?? null) === 'jessegall/code-commandments') {
                return;
            }
        }

        \JesseGall\CodeCommandments\Support\CommandmentsUpdater::ensureComposerScripts(
            $basePath,
            static fn (string $line) => null,
            static fn (string $line) => null,
        );

        @mkdir(dirname($marker), 0755, true);
        @file_put_contents($marker, "1\n");
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
