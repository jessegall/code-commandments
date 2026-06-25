<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Support\SyncHookInstaller;

/**
 * Install a git post-merge hook that auto-runs sync when composer.lock changes.
 * Thin adapter over {@see SyncHookInstaller}.
 */
class InstallSyncHookCommand extends Command
{
    protected $signature = 'commandments:install-sync-hook
        {--force : Overwrite an existing post-merge hook}';

    protected $description = 'Install a git post-merge hook that auto-runs sync --after=previous when composer.lock changes';

    public function handle(): int
    {
        return SyncHookInstaller::install(
            base_path(),
            (bool) $this->option('force'),
            '@php artisan commandments:sync --after=previous',
            $this->info(...),
            $this->error(...),
        ) === SyncHookInstaller::SUCCESS ? self::SUCCESS : self::FAILURE;
    }
}
