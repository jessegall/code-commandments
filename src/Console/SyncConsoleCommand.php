<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Support\ConfigLoader;
use JesseGall\CodeCommandments\Support\ConfigSyncer;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\VersionResolver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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
            ->addOption('after', null, InputOption::VALUE_REQUIRED, 'Only add prophets introduced after this version (e.g. 1.4.0). Pass `previous` to use the last synced version automatically. Prophets you removed before upgrading stay removed.')
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

        $after = $input->getOption('after');
        $versionResolver = new VersionResolver();

        if ($after === 'previous') {
            $after = $versionResolver->previousSyncedVersion($basePath);

            if ($after === null) {
                $output->writeln('<comment>No previous sync recorded — falling back to a full sync.</comment>');
            } else {
                $output->writeln("Using previous synced version: {$after}");
            }
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
                : '';
            $output->writeln("  + {$shortName} → {$entry['scroll']}{$versionTag}");
        }

        $count = count($result['added']);

        if ($input->getOption('dry-run')) {
            $output->writeln('');
            $output->writeln("{$count} new prophet(s) found (dry run, no changes made).");

            return Command::SUCCESS;
        }

        file_put_contents($configPath, $result['source']);

        $currentVersion = $versionResolver->currentVersion();

        if ($currentVersion !== null) {
            $versionResolver->recordSyncedVersion($basePath, $currentVersion);
        }

        $relativePath = str_starts_with($configPath, $basePath)
            ? ltrim(str_replace($basePath, '', $configPath), '/')
            : $configPath;

        $output->writeln('');
        $output->writeln("Synced {$count} new prophet(s) into {$relativePath}.");

        if ($currentVersion !== null) {
            $output->writeln("Recorded sync version {$currentVersion} in .commandments-last-synced");
        }

        return Command::SUCCESS;
    }

    private function isValidVersion(string $version): bool
    {
        return (bool) preg_match('/^\d+(\.\d+){0,2}(?:-[0-9A-Za-z-.]+)?$/', $version);
    }
}
