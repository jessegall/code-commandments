<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Support\ConfigLoader;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\ScaffoldService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ScaffoldConsoleCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('scaffold')
            ->setDescription('Generate recommended support classes (Option, FromArrayOnly, …) into your namespace')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config file')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite existing support classes')
            ->addOption('auto', null, InputOption::VALUE_NONE, 'Refresh only when scaffold.auto_refresh is enabled (session-start hook); otherwise do nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $basePath = getcwd();
        Environment::setBasePath($basePath);

        $resolved = ConfigLoader::resolve($input->getOption('config'), $basePath);

        if ($resolved === null) {
            $output->writeln('<error>No configuration file found.</error>');

            return Command::FAILURE;
        }

        ScaffoldService::generate(
            ConfigLoader::load($resolved)['scaffold'] ?? [],
            $basePath . '/app/Support',
            (bool) $input->getOption('auto'),
            (bool) $input->getOption('force'),
            fn (string $line) => $output->writeln($line),
        );

        return Command::SUCCESS;
    }
}
