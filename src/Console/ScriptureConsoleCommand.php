<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Contracts\SinRepenter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use JesseGall\PhpTypes\T_String;

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
        $output->writeln(T_String::empty());
        $output->writeln('IMPORTANT: Never commit code with sins. Fix all violations first.');
        $output->writeln(T_String::empty());

        foreach ($scrolls as $scroll) {
            if (!$registry->hasScroll($scroll)) {
                continue;
            }

            $output->writeln(strtoupper($scroll) . ':');

            $prophets = $registry->getProphets($scroll);

            foreach ($prophets as $prophet) {
                $className = str_replace('Prophet', T_String::empty(), class_basename($prophet));
                $canRepent = $prophet instanceof SinRepenter;

                $badge = $canRepent ? ' [AUTO-FIXABLE]' : T_String::empty();

                $output->writeln("- {$className}{$badge}: {$prophet->description()}");

                if ($detailed) {
                    $detailedDesc = $prophet->detailedDescription();
                    $lines = explode(T_String::NEWLINE, $detailedDesc);
                    foreach ($lines as $line) {
                        $output->writeln("  {$line}");
                    }
                    $output->writeln(T_String::empty());
                }
            }

            $output->writeln(T_String::empty());
        }

        $output->writeln('Check violations: commandments judge --next --git');
        $output->writeln('Auto-fix [AUTO-FIXABLE] sins: commandments repent  (do NOT hand-fix these)');
        $output->writeln('Report a false positive OR prophet bug (proactively!): commandments report --prophet=NAME --reason="what is wrong"');

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
        $shortName = str_replace('Prophet', T_String::empty(), $className);
        $canRepent = $prophet instanceof SinRepenter;

        $output->writeln(strtoupper($shortName));
        $output->writeln(T_String::empty());
        $output->writeln('REQUIREMENT: ' . $prophet->description());
        if ($canRepent) {
            $output->writeln('[AUTO-FIXABLE with: commandments repent]');
        }
        $output->writeln(T_String::empty());
        $output->writeln('You MUST follow this rule exactly as described below:');
        $output->writeln(T_String::empty());

        $detailedDesc = $prophet->detailedDescription();
        $output->writeln($detailedDesc);

        return Command::SUCCESS;
    }
}
