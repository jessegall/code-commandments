<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Support\ConfigLoader;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\SkillInstallService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class InstallSkillsConsoleCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('install-skills')
            ->setDescription('Install the Code Commandments skills into .claude/skills/')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config file')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite existing skill files')
            ->addOption('auto', null, InputOption::VALUE_NONE, 'Refresh only when skills.auto_refresh is enabled (session-start hook); otherwise do nothing');
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

        SkillInstallService::install(
            ConfigLoader::load($resolved),
            $basePath . '/.claude/skills',
            (bool) $input->getOption('auto'),
            (bool) $input->getOption('force'),
            $output->writeln(...),
        );

        return Command::SUCCESS;
    }
}
