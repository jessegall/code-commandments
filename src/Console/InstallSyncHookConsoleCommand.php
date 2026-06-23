<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\SyncHookInstaller;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Install a git post-merge hook that runs sync when composer.lock changes. Thin
 * adapter over {@see SyncHookInstaller}.
 */
class InstallSyncHookConsoleCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('install-sync-hook')
            ->setDescription('Install a git post-merge hook that auto-runs `sync --after=previous` when composer.lock changes')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite an existing post-merge hook');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $basePath = Environment::workingDirectory();
        Environment::setBasePath($basePath);

        return SyncHookInstaller::install(
            $basePath,
            (bool) $input->getOption('force'),
            'vendor/bin/commandments sync --after=previous',
            $output->writeln(...),
            fn (string $line) => $output->writeln('<error>' . $line . '</error>'),
        ) === SyncHookInstaller::SUCCESS ? Command::SUCCESS : Command::FAILURE;
    }
}
