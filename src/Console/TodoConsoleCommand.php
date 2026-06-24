<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Support\ConfigLoader;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimageRunner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Compact "what's still to fix here" for the CURRENT pilgrimage prophet — just the
 * remaining file:line locations, no scripture or banners. Lets the agent re-check
 * its progress on the current prophet at a glance without re-running the full
 * `next` output. Does NOT advance the walk.
 */
class TodoConsoleCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('todo')
            ->setDescription('List the still-unresolved file:line locations for the current pilgrimage prophet (compact; does not advance)')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to commandments.php config file')
            ->addOption('scroll', null, InputOption::VALUE_REQUIRED, 'Scroll to walk', 'backend');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $basePath = getcwd() ?: '.';
        Environment::setBasePath($basePath);

        $configPath = ConfigLoader::resolve($input->getOption('config'), $basePath);

        if ($configPath === null) {
            $output->writeln('<error>No configuration file found.</error> Run "commandments init" first.');

            return Command::FAILURE;
        }

        $runner = PilgrimageRunner::fromConfig($basePath, $configPath, (string) $input->getOption('scroll'));
        $step = $runner->peek();

        if ($step === null) {
            $output->writeln('<comment>No pilgrimage in progress.</comment> Run `commandments pilgrimage` to begin.');

            return Command::SUCCESS;
        }

        if (($step['complete'] ?? false) === true) {
            $output->writeln('✓ The pilgrimage is complete — nothing left to fix.');

            return Command::SUCCESS;
        }

        $prophet = $step['prophet'] ?? '?';
        $locations = $step['locations'] ?? [];

        if ($locations === []) {
            $output->writeln(sprintf('✓ %s is clean — run `commandments next` to advance.', $prophet));

            return Command::SUCCESS;
        }

        $output->writeln(sprintf('%s — %d still to resolve:', $prophet, count($locations)));

        foreach ($locations as $location) {
            $tag = ($location['autoFixable'] ?? false) === true ? ' [AUTO-FIXABLE]' : '';
            $output->writeln(sprintf('  %s:%s%s', $location['file'], $location['line'] ?? '?', $tag));
        }

        return Command::SUCCESS;
    }
}
