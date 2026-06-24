<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Support\ConfigLoader;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimageRunner;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimageState;
use JesseGall\CodeCommandments\Support\RepentService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Auto-fix ONLY the prophet the pilgrimage is currently on. When `next` lands on a
 * prophet whose findings are [AUTO-FIXABLE], run this to repent them in place — no
 * bulk pre-clear of the whole codebase, just the current step — then `next` to
 * advance. Scoped to the pilgrimage's current prophet + its frozen scope.
 */
class AutofixConsoleCommand extends Command
{
    use BootsStandalone;

    protected function configure(): void
    {
        $this
            ->setName('autofix')
            ->setDescription('Auto-fix the CURRENT pilgrimage prophet ([AUTO-FIXABLE] findings only), in place')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config file')
            ->addOption('scroll', null, InputOption::VALUE_REQUIRED, 'Scroll to walk', 'backend')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be fixed without making changes');
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

        $state = PilgrimageState::load($basePath);

        if ($state === null || $state->complete) {
            $output->writeln('<comment>No pilgrimage in progress.</comment> Run `commandments pilgrimage` to begin, walk to an [AUTO-FIXABLE] prophet, then `autofix`.');

            return Command::SUCCESS;
        }

        $runner = PilgrimageRunner::fromConfig($basePath, $configPath, (string) $input->getOption('scroll'));
        $step = $runner->peek();
        $prophet = $step['prophet'] ?? null;

        if ($prophet === null) {
            $output->writeln('<comment>No current prophet to auto-fix.</comment>');

            return Command::SUCCESS;
        }

        $output->writeln("Auto-fixing the current prophet: {$prophet}");

        [$registry, $manager] = $this->bootEnvironment($input->getOption('config'));

        $service = new RepentService($manager, $registry, $output->writeln(...));

        // Scope repent to THIS prophet over the pilgrimage's frozen file set, so it
        // touches only the current step — never the whole codebase.
        return $service->run([
            'prophet' => $prophet,
            'files' => $state->scope,
            'dry_run' => (bool) $input->getOption('dry-run'),
        ]) === RepentService::SUCCESS ? Command::SUCCESS : Command::FAILURE;
    }
}
