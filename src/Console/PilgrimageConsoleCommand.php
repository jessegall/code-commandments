<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Support\ConfigLoader;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimageIndexCache;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimagePresenter;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimageRunner;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimageStarter;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimageState;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
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
            ->addArgument('prophet', InputArgument::OPTIONAL, 'Constrain the walk to ONE prophet (partial name, like judge --prophet) — repent only its findings')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to commandments.php config file')
            ->addOption('scroll', null, InputOption::VALUE_REQUIRED, 'Scroll to walk', 'backend')
            ->addOption('is-complete', null, InputOption::VALUE_NONE, 'INTERNAL: exit 0 only if THIS session has genuinely walked the whole pilgrimage (the pre-push gate uses this to grant a completed walk one push). Recomputed from the cursor — a hand-written complete flag does not pass')
            ->addOption('clear', null, InputOption::VALUE_NONE, 'INTERNAL: discard the pilgrimage state (the pre-push gate consumes a completed walk so the next push re-arms the gate)');
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

        if ((bool) $input->getOption('clear')) {
            PilgrimageState::clear($basePath);
            PilgrimageIndexCache::clear($basePath);

            return Command::SUCCESS;
        }

        $runner = PilgrimageRunner::fromConfig($basePath, $configPath, (string) $input->getOption('scroll'));

        if ((bool) $input->getOption('is-complete')) {
            return $runner->isComplete() ? Command::SUCCESS : Command::FAILURE;
        }

        $prophet = $input->getArgument('prophet');

        if (is_string($prophet) && in_array(strtolower($prophet), ['next', 'todo', 'autofix', 'abandon', 'absolve', 'report', 'judge', 'repent', 'skills', 'scripture'], true)) {
            $output->writeln("<comment>`{$prophet}` is a top-level command, not a pilgrimage argument.</comment> Did you mean `commandments {$prophet}`? (the pilgrimage's optional argument is a PROPHET name filter.)");

            return Command::SUCCESS;
        }

        $step = PilgrimageStarter::start($runner, $basePath, is_string($prophet) ? $prophet : null, $output->writeln(...));

        if ($step === null) {
            return Command::SUCCESS;
        }

        $output->writeln(sprintf('<info>The pilgrimage begins.</info> %d station(s) ahead.', $runner->totalDoctrines()));

        foreach (PilgrimagePresenter::render($step, $runner) as $line) {
            $output->writeln($line);
        }

        return Command::SUCCESS;
    }
}
