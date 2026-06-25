<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Support\ConfigSyncer;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\SyncService;
use JesseGall\CodeCommandments\Support\VersionResolver;
use JesseGall\PhpTypes\T_String;

/**
 * Add newly available prophets to the published config file.
 *
 * After updating the package, run this command to automatically
 * register any new prophets that were added in the update.
 */
class SyncCommand extends Command
{
    protected $signature = 'commandments:sync
        {--after= : Override the floor: only add prophets introduced after this version (e.g. 1.4.0), or `previous` for the last synced version.}
        {--all : OPT OUT of removal-respecting sync: add EVERY available prophet missing from the config. Without this, sync NEVER re-adds a prophet you removed.}
        {--dry-run : Show what would be added without modifying the file}';

    protected $description = 'Add newly available prophets to your config file';

    public function handle(): int
    {
        $configPath = config_path('commandments.php');

        if (! file_exists($configPath)) {
            $this->error('Config file not found. Run "php artisan vendor:publish --tag=commandments-config" first.');

            return self::FAILURE;
        }

        if (! $this->option('dry-run')) {
            foreach (SyncService::refreshSideEffects(base_path(), config('commandments', [])) as $line) {
                $this->line($line);
            }
        }

        $after = $this->option('after');
        $versionResolver = new VersionResolver();
        $basePath = base_path();

        // Removal-respecting is the DEFAULT — a sync only adds prophets introduced
        // AFTER a floor version, so it NEVER re-adds one you intentionally removed
        // (they all carry #[IntroducedIn]). Floor = the last synced version, else the
        // currently-installed version (so an unknown baseline still adds nothing).
        // `--all` opts out for a full re-sync; `--after=X` overrides the floor.
        if (! $this->option('all')) {
            if ($after === null || $after === 'previous') {
                $after = $versionResolver->previousSyncedVersion($basePath)
                    ?? $versionResolver->currentVersion();
            }

            if ($after === null) {
                $this->warn('No sync baseline and no --all — taking your config as intentional, adding nothing. Use `sync --all` for a full (re-)sync.');

                return self::SUCCESS;
            }

            $this->info("Adding only prophets introduced after: {$after}");
        } else {
            $after = null; // explicit full sync
        }

        if ($after !== null && ! $this->isValidVersion($after)) {
            $this->error("--after must be a valid semver string (got: {$after})");

            return self::FAILURE;
        }

        $syncer = new ConfigSyncer();
        $result = $syncer->sync($configPath, $after);

        if (empty($result['added'])) {
            $message = $after !== null
                ? "No prophets introduced after {$after}. Nothing to sync."
                : 'All prophets are already registered. Nothing to sync.';
            $this->info($message);

            return self::SUCCESS;
        }

        foreach ($result['added'] as $entry) {
            $shortName = class_basename($entry['class']);
            $versionTag = $entry['introduced_in'] !== null
                ? " (introduced in {$entry['introduced_in']})"
                : T_String::empty();
            $this->line("  + {$shortName} → {$entry['scroll']}{$versionTag}");
        }

        $count = count($result['added']);

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->info("{$count} new prophet(s) found (dry run, no changes made).");

            return self::SUCCESS;
        }

        file_put_contents($configPath, $result['source']);

        $currentVersion = $versionResolver->currentVersion();

        if ($currentVersion !== null) {
            $versionResolver->recordSyncedVersion($basePath, $currentVersion);
        }

        $this->newLine();
        $this->info("Synced {$count} new prophet(s) into config/commandments.php.");

        if ($currentVersion !== null) {
            $this->line("Recorded sync version {$currentVersion} in .commandments/last-synced");
        }

        return self::SUCCESS;
    }

    private function isValidVersion(string $version): bool
    {
        return (bool) preg_match('/^\d+(\.\d+){0,2}(?:-[0-9A-Za-z-.]+)?$/', $version);
    }
}
