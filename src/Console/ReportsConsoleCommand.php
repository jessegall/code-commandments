<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use Illuminate\Filesystem\Filesystem;
use JesseGall\CodeCommandments\Support\ConfigLoader;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\ReportsService;
use JesseGall\CodeCommandments\Tracking\JsonConfessionTracker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use JesseGall\PhpTypes\T_String;

/**
 * Track the prophet reports this project filed and surface the ones resolved
 * upstream. Thin adapter over {@see ReportsService}; only the tracker
 * construction is standalone-specific.
 */
class ReportsConsoleCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('reports')
            ->setDescription('Show the status of prophet reports this project filed (resolved upstream yet?)')
            ->addOption('check', null, InputOption::VALUE_NONE, 'Quiet hook mode: print only newly-resolved reports');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $basePath = Environment::workingDirectory();

        ReportsService::check(
            $this->tracker($basePath),
            $basePath,
            (bool) $input->getOption('check'),
            $output->writeln(...),
        );

        return Command::SUCCESS;
    }

    private function tracker(string $basePath): JsonConfessionTracker
    {
        Environment::setBasePath($basePath);

        $tabletPath = Environment::basePath('.commandments/confessions.json');
        $resolved = ConfigLoader::resolve(null, $basePath);

        if ($resolved !== null) {
            $configured = ConfigLoader::load($resolved)['confession']['tablet_path'] ?? null;

            if (is_string($configured) && T_String::isNotEmpty($configured)) {
                $tabletPath = $configured;
            }
        }

        return new JsonConfessionTracker($tabletPath, new Filesystem());
    }
}
