<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Support\ConfigLoader;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimagePresenter;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimageRunner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Advance the pilgrimage by one finding. Forward-only: it never re-scans a pillar
 * already walked, so the agent cannot bounce between findings that re-introduce
 * one another. Run `pilgrimage` first to begin.
 */
class NextConsoleCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('next')
            ->setDescription('Advance the pilgrimage to the next finding (forward-only)')
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
        $state = $runner->advance();

        if ($state === null) {
            $output->writeln('<comment>No pilgrimage in progress.</comment> Run `commandments pilgrimage` to begin.');

            return Command::SUCCESS;
        }

        foreach (PilgrimagePresenter::render($state, $runner) as $line) {
            $output->writeln($line);
        }

        return Command::SUCCESS;
    }
}
