<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Support\Absolver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use JesseGall\PhpTypes\T_String;

class AbsolveConsoleCommand extends Command
{
    use BootsStandalone;

    protected function configure(): void
    {
        $this
            ->setName('absolve')
            ->setDescription('Absolve a single finding by fingerprint, with a required reason')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config file')
            ->addOption('fingerprint', null, InputOption::VALUE_REQUIRED, 'The finding fingerprint shown by judge --next')
            ->addOption('reason', null, InputOption::VALUE_REQUIRED, 'Why the rule does not apply here (required)')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Baseline the queue: absolve every current advisory finding at once (sins still block)')
            ->addOption('clear', null, InputOption::VALUE_NONE, 'Remove every ordinary absolution (post-commit reset so nothing stays hidden); report-linked absolutions persist until their issue is answered');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        [$registry, $manager, $tracker] = $this->bootEnvironment($input->getOption('config'));

        if ((bool) $input->getOption('clear')) {
            $cleared = $tracker->clearFindingAbsolutions();
            $output->writeln("<info>Cleared {$cleared} absolution(s). Every finding will be re-evaluated from scratch.</info>");

            return Command::SUCCESS;
        }

        if ((bool) $input->getOption('all')) {
            $result = (new Absolver($manager, $registry, $tracker))->absolveAll($input->getOption('reason'));

            $output->writeln("<info>Baselined the queue: absolved {$result['absolved']} advisory finding(s).</info>");

            if ($result['blocking_sins'] > 0) {
                $output->writeln("<comment>{$result['blocking_sins']} sin(s) cannot be absolved and still block — fix them with: commandments judge --next</comment>");
            }

            return Command::SUCCESS;
        }

        $fingerprint = $input->getOption('fingerprint');

        if (! is_string($fingerprint) || T_String::isBlank($fingerprint)) {
            $output->writeln('<error>--fingerprint is required (copy it from judge --next).</error>');

            return Command::FAILURE;
        }

        $result = (new Absolver($manager, $registry, $tracker))
            ->absolve(trim($fingerprint), $input->getOption('reason'));

        if ($result['status'] === Absolver::STATUS_OK) {
            $output->writeln('<info>' . $result['message'] . '</info>');

            return Command::SUCCESS;
        }

        $output->writeln('<error>' . $result['message'] . '</error>');

        return Command::FAILURE;
    }
}
