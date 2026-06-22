<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RepentConsoleCommand extends Command
{
    use BootsStandalone;

    protected function configure(): void
    {
        $this
            ->setName('repent')
            ->setDescription('Auto-fix findings that can be automatically resolved — sins and [AUTO-FIXABLE] warnings (no severity bump needed)')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config file')
            ->addOption('scroll', null, InputOption::VALUE_REQUIRED, 'Filter by specific scroll (group)')
            ->addOption('prophet', null, InputOption::VALUE_REQUIRED, 'Use a specific prophet for repentance')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Repent sins in a specific file')
            ->addOption('files', null, InputOption::VALUE_REQUIRED, 'Repent sins in specific files (comma-separated)')
            ->addOption('git', null, InputOption::VALUE_NONE, 'Only repent files that are new or changed in git')
            ->addOption('input', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Input for a parameterized fixer, repeatable: --input key=value')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be fixed without making changes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        [$registry, $manager] = $this->bootEnvironment($input->getOption('config'));

        $service = new \JesseGall\CodeCommandments\Support\RepentService(
            $manager,
            $registry,
            fn (string $line) => $output->writeln($line),
        );

        return $service->run([
            'scroll' => $input->getOption('scroll'),
            'prophet' => $input->getOption('prophet'),
            'file' => $input->getOption('file'),
            'files' => $input->getOption('files') ? array_map('trim', explode(',', $input->getOption('files'))) : [],
            'git' => (bool) $input->getOption('git'),
            'dry_run' => (bool) $input->getOption('dry-run'),
            'input' => (array) $input->getOption('input'),
        ]) === \JesseGall\CodeCommandments\Support\RepentService::SUCCESS ? Command::SUCCESS : Command::FAILURE;
    }
}
