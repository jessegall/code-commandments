<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Support\ConfigLoader;
use JesseGall\CodeCommandments\Support\ConfigSyncer;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\GitignoreInstaller;
use JesseGall\CodeCommandments\Support\SyncService;
use JesseGall\CodeCommandments\Support\VersionResolver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use JesseGall\PhpTypes\T_String;

/**
 * Add newly available prophets to an existing config file.
 *
 * After updating the package, run this command to automatically
 * register any new prophets that were added in the update.
 */
class SyncConsoleCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('sync')
            ->setDescription('Add newly available prophets to your config file')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to commandments.php config file')
            ->addOption('after', null, InputOption::VALUE_REQUIRED, 'Override the floor: only add prophets introduced after this version (e.g. 1.4.0), or `previous` for the last synced version.')
            ->addOption('all', null, InputOption::VALUE_NONE, 'OPT OUT of removal-respecting sync: add EVERY available prophet missing from the config (initial setup / deliberate full re-sync). Without this, sync NEVER re-adds a prophet you removed.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be added without modifying the file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $basePath = getcwd();
        Environment::setBasePath($basePath);

        $configPath = ConfigLoader::resolve($input->getOption('config'), $basePath);

        if ($configPath === null) {
            $output->writeln('No configuration file found. Run "commandments init" first.');

            return Command::FAILURE;
        }

        if (! $input->getOption('dry-run')) {
            foreach (SyncService::refreshSideEffects($basePath, ConfigLoader::load($configPath)) as $line) {
                $output->writeln($line);
            }
        }

        $after = $input->getOption('after');
        $versionResolver = new VersionResolver();

        // Removal-respecting is the DEFAULT — a sync only adds prophets introduced
        // AFTER a floor version, so it NEVER re-adds one you intentionally removed
        // (they all carry #[IntroducedIn]). Floor = the last synced version, else the
        // currently-installed version (so an unknown baseline still adds nothing,
        // taking your config as intentional). `--all` opts out for a full re-sync;
        // `--after=X` overrides the floor (`previous` = last synced).
        if (! $input->getOption('all')) {
            if ($after === null || $after === 'previous') {
                $after = $versionResolver->previousSyncedVersion($basePath)
                    ?? $versionResolver->currentVersion();
            }

            if ($after === null) {
                $output->writeln('<comment>No sync baseline and no --all — taking your config as intentional, adding nothing. Use `sync --all` for a full (re-)sync.</comment>');

                return Command::SUCCESS;
            }

            $output->writeln("Adding only prophets introduced after: {$after}");
        } else {
            $after = null; // explicit full sync
        }

        if ($after !== null && ! $this->isValidVersion($after)) {
            $output->writeln("<error>--after must be a valid semver string (got: {$after})</error>");

            return Command::FAILURE;
        }

        $syncer = new ConfigSyncer();
        $result = $syncer->sync($configPath, $after);

        if (empty($result['added'])) {
            $message = $after !== null
                ? "No prophets introduced after {$after}. Nothing to sync."
                : 'All prophets are already registered. Nothing to sync.';
            $output->writeln($message);

            return Command::SUCCESS;
        }

        foreach ($result['added'] as $entry) {
            $shortName = class_basename($entry['class']);
            $versionTag = $entry['introduced_in'] !== null
                ? " (introduced in {$entry['introduced_in']})"
                : T_String::empty();
            $output->writeln("  + {$shortName} → {$entry['scroll']}{$versionTag}");
        }

        $count = count($result['added']);

        if ($input->getOption('dry-run')) {
            $output->writeln(T_String::empty());
            $output->writeln("{$count} new prophet(s) found (dry run, no changes made).");

            return Command::SUCCESS;
        }

        file_put_contents($configPath, $result['source']);

        $currentVersion = $versionResolver->currentVersion();

        if ($currentVersion !== null) {
            $versionResolver->recordSyncedVersion($basePath, $currentVersion);
        }

        $relativePath = str_starts_with($configPath, $basePath)
            ? ltrim(str_replace($basePath, T_String::empty(), $configPath), '/')
            : $configPath;

        $output->writeln(T_String::empty());
        $output->writeln("Synced {$count} new prophet(s) into {$relativePath}.");

        if ($currentVersion !== null) {
            $output->writeln("Recorded sync version {$currentVersion} in .commandments/last-synced");
        }

        return Command::SUCCESS;
    }

    private function isValidVersion(string $version): bool
    {
        return (bool) preg_match('/^\d+(\.\d+){0,2}(?:-[0-9A-Za-z-.]+)?$/', $version);
    }
}
