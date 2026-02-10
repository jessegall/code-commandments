<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Support\ConfigSyncer;

/**
 * Add newly available prophets to the published config file.
 *
 * After updating the package, run this command to automatically
 * register any new prophets that were added in the update.
 */
class SyncCommand extends Command
{
    protected $signature = 'commandments:sync
        {--dry-run : Show what would be added without modifying the file}';

    protected $description = 'Add newly available prophets to your config file';

    public function handle(): int
    {
        $configPath = config_path('commandments.php');

        if (! file_exists($configPath)) {
            $this->error('Config file not found. Run "php artisan vendor:publish --tag=commandments-config" first.');

            return self::FAILURE;
        }

        $syncer = new ConfigSyncer();
        $result = $syncer->sync($configPath);

        if (empty($result['added'])) {
            $this->info('All prophets are already registered. Nothing to sync.');

            return self::SUCCESS;
        }

        foreach ($result['added'] as $entry) {
            $shortName = class_basename($entry['class']);
            $this->line("  + {$shortName} → {$entry['scroll']}");
        }

        $count = count($result['added']);

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->info("{$count} new prophet(s) found (dry run, no changes made).");

            return self::SUCCESS;
        }

        file_put_contents($configPath, $result['source']);

        $this->newLine();
        $this->info("Synced {$count} new prophet(s) into config/commandments.php.");

        return self::SUCCESS;
    }
}
