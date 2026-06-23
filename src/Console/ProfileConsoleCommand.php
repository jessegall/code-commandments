<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Support\ConfigLoader;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\Profiles\ProfileService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Show, list, or switch the active code-commandments profile. Thin adapter over
 * {@see ProfileService}.
 */
class ProfileConsoleCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('profile')
            ->setDescription('Show, list, or switch the active code-commandments profile (disabled/grind/phased/sins-only)')
            ->addArgument('name', InputArgument::OPTIONAL, 'Profile to switch to, or "list" to see them all')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config file')
            ->addOption('brief', null, InputOption::VALUE_NONE, 'Print the active profile briefing (session-start hook)')
            ->addOption('drift-check', null, InputOption::VALUE_NONE, 'Re-brief only when the profile changed (per-turn hook)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $basePath = getcwd() ?: '.';
        Environment::setBasePath($basePath);

        $service = new ProfileService($basePath, $this->loadConfig($input->getOption('config'), $basePath));

        $emit = $output->writeln(...);
        $error = fn (string $line) => $output->writeln('<error>' . $line . '</error>');

        if ($input->getOption('brief')) {
            $service->brief($emit);

            return Command::SUCCESS;
        }

        if ($input->getOption('drift-check')) {
            $service->driftCheck($emit);

            return Command::SUCCESS;
        }

        $name = $input->getArgument('name');

        if ($name === null || $name === 'show') {
            $service->show($emit);

            return Command::SUCCESS;
        }

        if ($name === 'list') {
            $service->list($emit);

            return Command::SUCCESS;
        }

        return $service->switch($name, $emit, $error) === ProfileService::SUCCESS ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Best-effort config load (for the plan_loop flag). A profile switch must work
     * even in a repo without a commandments config.
     *
     * @return array<string, mixed>
     */
    private function loadConfig(?string $configPath, string $basePath): array
    {
        $resolved = ConfigLoader::resolve($configPath, $basePath);

        return $resolved === null ? [] : ConfigLoader::load($resolved);
    }
}
