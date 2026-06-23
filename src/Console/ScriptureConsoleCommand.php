<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ScriptureConsoleCommand extends Command
{
    use BootsStandalone;

    protected function configure(): void
    {
        $this
            ->setName('scripture')
            ->setDescription('List all commandments and their descriptions')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config file')
            ->addOption('scroll', null, InputOption::VALUE_REQUIRED, 'Filter by specific scroll (group)')
            ->addOption('prophet', null, InputOption::VALUE_REQUIRED, 'Show details for a specific prophet')
            ->addOption('detailed', null, InputOption::VALUE_NONE, 'Show full descriptions with examples');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        [$registry] = $this->bootEnvironment($input->getOption('config'));

        return \JesseGall\CodeCommandments\Support\ScriptureService::render(
            $registry,
            $input->getOption('scroll'),
            $input->getOption('prophet'),
            (bool) $input->getOption('detailed'),
            \JesseGall\CodeCommandments\Support\ClaudeHooksInstaller::STANDALONE,
            $output->writeln(...),
        ) === \JesseGall\CodeCommandments\Support\ScriptureService::SUCCESS ? Command::SUCCESS : Command::FAILURE;
    }
}
