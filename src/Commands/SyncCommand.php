<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Support\ConfigSyncer;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\VersionResolver;

/**
 * Add newly available prophets to the published config file.
 *
 * After updating the package, run this command to automatically
 * register any new prophets that were added in the update.
 */
class SyncCommand extends Command
{
    protected $signature = 'commandments:sync
        {--after= : Only add prophets introduced after this version (e.g. 1.4.0). Pass `previous` to use the last synced version automatically.}
        {--dry-run : Show what would be added without modifying the file}';

    protected $description = 'Add newly available prophets to your config file';

    public function handle(): int
    {
        $configPath = config_path('commandments.php');

        if (! file_exists($configPath)) {
            $this->error('Config file not found. Run "php artisan vendor:publish --tag=commandments-config" first.');

            return self::FAILURE;
        }

        $after = $this->option('after');
        $versionResolver = new VersionResolver();
        $basePath = base_path();

        if ($after === 'previous') {
            $after = $versionResolver->previousSyncedVersion($basePath);

            if ($after === null) {
                $this->warn('No previous sync recorded — falling back to a full sync.');
            } else {
                $this->info("Using previous synced version: {$after}");
            }
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
                : '';
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
            $this->line("Recorded sync version {$currentVersion} in .commandments-last-synced");
        }

        return self::SUCCESS;
    }

    private function isValidVersion(string $version): bool
    {
        return (bool) preg_match('/^\d+(\.\d+){0,2}(?:-[0-9A-Za-z-.]+)?$/', $version);
    }
}
