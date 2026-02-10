<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Support\ConfigLoader;
use JesseGall\CodeCommandments\Support\ConfigSyncer;
use JesseGall\CodeCommandments\Support\Environment;
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

        $syncer = new ConfigSyncer();
        $result = $syncer->sync($configPath);

        if (empty($result['added'])) {
            $output->writeln('All prophets are already registered. Nothing to sync.');

            return Command::SUCCESS;
        }

        foreach ($result['added'] as $entry) {
            $shortName = class_basename($entry['class']);
            $output->writeln("  + {$shortName} → {$entry['scroll']}");
        }

        $count = count($result['added']);

        if ($input->getOption('dry-run')) {
            $output->writeln('');
            $output->writeln("{$count} new prophet(s) found (dry run, no changes made).");

            return Command::SUCCESS;
        }

        file_put_contents($configPath, $result['source']);

        $relativePath = str_starts_with($configPath, $basePath)
            ? ltrim(str_replace($basePath, '', $configPath), '/')
            : $configPath;

        $output->writeln('');
        $output->writeln("Synced {$count} new prophet(s) into {$relativePath}.");

        return Command::SUCCESS;
    }
}
