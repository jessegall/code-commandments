<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Support\Absolver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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
            ->addOption('reason', null, InputOption::VALUE_REQUIRED, 'Why the rule does not apply here (required)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        [$registry, $manager, $tracker] = $this->bootEnvironment($input->getOption('config'));

        $fingerprint = $input->getOption('fingerprint');

        if (! is_string($fingerprint) || trim($fingerprint) === '') {
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
