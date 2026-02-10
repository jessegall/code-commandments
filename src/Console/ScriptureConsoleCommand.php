<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Contracts\SinRepenter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ScriptureConsoleCommand extends Command
{
    use BootsStandalone;

    protected function configure(): void
    {
        $this
            ->setName('scripture')
            ->setDescription('List all commandments and their descriptions')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config file')
            ->addOption('scroll', null, InputOption::VALUE_REQUIRED, 'Filter by specific scroll (group)')
            ->addOption('prophet', null, InputOption::VALUE_REQUIRED, 'Show details for a specific prophet')
            ->addOption('detailed', null, InputOption::VALUE_NONE, 'Show full descriptions with examples');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        [$registry] = $this->bootEnvironment($input->getOption('config'));

        $scrollFilter = $input->getOption('scroll');
        $prophetFilter = $input->getOption('prophet');
        $detailed = $input->getOption('detailed') || $prophetFilter;

        $scrolls = $scrollFilter
            ? [$scrollFilter]
            : $registry->getScrolls();

        if ($prophetFilter) {
            return $this->showProphetDetails($output, $registry, $prophetFilter);
        }

        $output->writeln('CODE COMMANDMENTS');
        $output->writeln('');
        $output->writeln('IMPORTANT: Never commit code with sins. Fix all violations first.');
        $output->writeln('');

        foreach ($scrolls as $scroll) {
            if (!$registry->hasScroll($scroll)) {
                continue;
            }

            $output->writeln(strtoupper($scroll) . ':');

            $prophets = $registry->getProphets($scroll);

            foreach ($prophets as $prophet) {
                $className = str_replace('Prophet', '', class_basename($prophet));
                $canRepent = $prophet instanceof SinRepenter;

                $badge = $canRepent ? ' [AUTO-FIXABLE]' : '';

                $output->writeln("- {$className}{$badge}: {$prophet->description()}");

                if ($detailed) {
                    $detailedDesc = $prophet->detailedDescription();
                    $lines = explode("\n", $detailedDesc);
                    foreach ($lines as $line) {
                        $output->writeln("  {$line}");
                    }
                    $output->writeln('');
                }
            }

            $output->writeln('');
        }

        $output->writeln('Check violations: commandments judge');
        $output->writeln('Auto-fix sins: commandments repent');

        return Command::SUCCESS;
    }

    private function showProphetDetails(OutputInterface $output, $registry, string $prophetFilter): int
    {
        $found = $registry->findProphet($prophetFilter);

        if (!$found) {
            $output->writeln("Prophet '{$prophetFilter}' not found.");

            return Command::FAILURE;
        }

        $prophet = $found['prophet'];
        $className = class_basename($prophet);
        $shortName = str_replace('Prophet', '', $className);
        $canRepent = $prophet instanceof SinRepenter;

        $output->writeln(strtoupper($shortName));
        $output->writeln('');
        $output->writeln('REQUIREMENT: ' . $prophet->description());
        if ($canRepent) {
            $output->writeln('[AUTO-FIXABLE with: commandments repent]');
        }
        $output->writeln('');
        $output->writeln('You MUST follow this rule exactly as described below:');
        $output->writeln('');

        $detailedDesc = $prophet->detailedDescription();
        $output->writeln($detailedDesc);

        return Command::SUCCESS;
    }
}
