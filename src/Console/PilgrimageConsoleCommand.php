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
 * Begin the forward-only walk through the doctrines, pillar by pillar. Resets any
 * prior pilgrimage and stops at the first pillar that has findings — dispatching
 * only that pillar's handful of prophets, so each step is fast. The agent fixes
 * or absolves, then runs `next` to walk on.
 */
class PilgrimageConsoleCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('pilgrimage')
            ->setDescription('Begin the forward-only doctrine walk (resets state; `next` advances it)')
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
        $state = $runner->begin();

        $output->writeln(sprintf('<info>The pilgrimage begins.</info> %d doctrines ahead.', $runner->totalDoctrines()));

        foreach (PilgrimagePresenter::render($state, $runner) as $line) {
            $output->writeln($line);
        }

        return Command::SUCCESS;
    }
}
