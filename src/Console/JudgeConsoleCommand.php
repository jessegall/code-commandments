<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class JudgeConsoleCommand extends Command
{
    use BootsStandalone;

    protected function configure(): void
    {
        $this
            ->setName('judge')
            ->setDescription('Judge the codebase for sins against the commandments')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config file')
            ->addOption('scroll', null, InputOption::VALUE_REQUIRED, 'Filter by specific scroll (group)')
            ->addOption('prophet', null, InputOption::VALUE_REQUIRED, 'Summon a specific prophet by name')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Judge a specific file')
            ->addOption('files', null, InputOption::VALUE_REQUIRED, 'Judge specific files (comma-separated)')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Override the scroll path and target a specific directory (bypasses all excludes — use to scan subtrees regardless of config)')
            ->addOption('git', null, InputOption::VALUE_NONE, 'Only judge files that are new or changed in git')
            ->addOption('staged', null, InputOption::VALUE_NONE, 'Only judge files staged for commit (what the pre-commit gate uses)')
            ->addOption('branch', null, InputOption::VALUE_NONE, 'Judge everything changed since the branch base, INCLUDING committed work (survives intermediate commits — the grind reckoning)')
            ->addOption('absolve', null, InputOption::VALUE_NONE, 'Mark files as absolved after confession')
            ->addOption('no-cache', null, InputOption::VALUE_NONE, 'Force a fresh judge — never read the findings cache (the pre-commit gate uses this to stay authoritative)')
            ->addOption('next', null, InputOption::VALUE_NONE, 'Show exactly one finding at a time (fix or absolve to advance)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        [$registry, $manager, $tracker] = $this->bootEnvironment($input->getOption('config'));

        $service = new \JesseGall\CodeCommandments\Support\JudgeService(
            $manager,
            $registry,
            $tracker,
            'commandments',
            ' ',
            fn (string $line) => $output->writeln($line),
            fn (string $line) => $output->writeln('<error>' . $line . '</error>'),
        );

        return $service->run([
            'scroll' => $input->getOption('scroll'),
            'prophet' => $input->getOption('prophet'),
            'file' => $input->getOption('file'),
            'files' => $input->getOption('files') ? array_map('trim', explode(',', $input->getOption('files'))) : [],
            'path' => $input->getOption('path'),
            'git' => (bool) $input->getOption('git'),
            'staged' => (bool) $input->getOption('staged'),
            'branch' => (bool) $input->getOption('branch'),
            'absolve' => (bool) $input->getOption('absolve'),
            'no_cache' => (bool) $input->getOption('no-cache'),
            'next' => (bool) $input->getOption('next'),
        ]) === \JesseGall\CodeCommandments\Support\JudgeService::SUCCESS ? Command::SUCCESS : Command::FAILURE;
    }
}
