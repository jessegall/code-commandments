<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Support\ConfigLoader;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\Skills\SkillInstaller;
use JesseGall\CodeCommandments\Support\Skills\SkillReporter;
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
            ->setDescription('Install the Code Commandments skills into .claude/skills/commandments/')
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

        $config = ConfigLoader::load($resolved);
        $skills = $config['skills'] ?? [];

        // Auto-refresh implies force + the do-not-edit banner.
        $autoRefresh = (bool) ($skills['auto_refresh'] ?? false);

        // The `--auto` hook is a no-op unless auto-refresh is on.
        if ((bool) $input->getOption('auto') && ! $autoRefresh) {
            return Command::SUCCESS;
        }

        // Skill examples use the consumer's scaffold namespace.
        $namespace = $config['scaffold']['namespace'] ?? 'App\\Support';
        $except = $skills['except'] ?? [];
        $targetRoot = $basePath . '/.claude/skills/commandments';

        $force = $autoRefresh || (bool) $input->getOption('force');

        $results = SkillInstaller::packaged()
            ->install($namespace, $targetRoot, $force, $except, $autoRefresh);

        $installed = SkillReporter::report($results, fn (string $line) => $output->writeln($line));

        $output->writeln($installed > 0
            ? "<info>Installed {$installed} skill(s) into .claude/skills/commandments/.</info>"
            : 'All skills already present — nothing to install.');

        return Command::SUCCESS;
    }
}
