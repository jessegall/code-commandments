<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Support\CommandmentsUpdater;
use JesseGall\CodeCommandments\Support\Environment;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The one command to stay current — what Composer's post-update / post-install
 * scripts run. It wires those scripts into composer.json (self-installing, no
 * plugin) and then syncs: new prophets register, the .gitignore block + active
 * profile re-assert, scaffold and skills refresh.
 */
class UpdateConsoleCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('update')
            ->setDescription('Stay current: wire the composer lifecycle scripts, then sync prophets / scaffold / skills / hooks')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to commandments.php config file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $basePath = getcwd() ?: '.';
        Environment::setBasePath($basePath);

        return CommandmentsUpdater::run(
            $basePath,
            function () use ($input, $output): int {
                $sync = $this->getApplication()?->find('sync');

                if ($sync === null) {
                    return Command::SUCCESS;
                }

                $args = ['--after' => 'previous'];

                if ($input->getOption('config') !== null) {
                    $args['--config'] = $input->getOption('config');
                }

                return $sync->run(new ArrayInput($args), $output);
            },
            $output->writeln(...),
            fn (string $line) => $output->writeln('<error>' . $line . '</error>'),
        );
    }
}
