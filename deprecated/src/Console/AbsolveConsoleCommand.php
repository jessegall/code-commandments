<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Support\AbsolveService;
use JesseGall\CodeCommandments\Support\Environment;
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
            ->setDescription('Absolve a single finding (warning OR sin) by fingerprint/location, with a required reason')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config file')
            ->addOption('fingerprint', null, InputOption::VALUE_REQUIRED, 'The finding fingerprint shown by judge --next')
            ->addOption('at', null, InputOption::VALUE_REQUIRED, 'Target a finding by location instead of a fingerprint — path:line (or path:from-to), exactly as judge prints it; combine with --prophet to disambiguate ties')
            ->addOption('reason', null, InputOption::VALUE_REQUIRED, 'Why the rule does not apply / is consciously accepted here (required; sins included)')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Baseline the queue: absolve every current advisory finding at once (sins still block)')
            ->addOption('warnings', null, InputOption::VALUE_NONE, 'Batch-absolve every WARNING in scope under one --reason; hard-refuses if any sin is in scope (absolves nothing)')
            ->addOption('scope', null, InputOption::VALUE_REQUIRED, 'Limit --warnings to changed files: "git" (vs tracked state) or "staged" (the index)')
            ->addOption('prophet', null, InputOption::VALUE_REQUIRED, 'Limit --warnings to one prophet (partial name match), e.g. --prophet=DuplicateCode — one scan, not one-per-finding')
            ->addOption('clear', null, InputOption::VALUE_NONE, 'Remove every ordinary absolution (post-commit reset so nothing stays hidden); report-linked absolutions persist until their issue is answered');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        [$registry, $manager, $tracker] = $this->bootEnvironment($input->getOption('config'));

        return AbsolveService::run(
            $manager,
            $registry,
            $tracker,
            [
                'clear' => (bool) $input->getOption('clear'),
                'warnings' => (bool) $input->getOption('warnings'),
                'scope' => $input->getOption('scope'),
                'prophet' => $input->getOption('prophet'),
                'all' => (bool) $input->getOption('all'),
                'fingerprint' => $input->getOption('fingerprint'),
                'at' => $input->getOption('at'),
                'reason' => $input->getOption('reason'),
            ],
            Environment::workingDirectory(),
            fn (string $line) => $output->writeln('<info>' . $line . '</info>'),
            fn (string $line) => $output->writeln('<comment>' . $line . '</comment>'),
        ) === AbsolveService::SUCCESS ? Command::SUCCESS : Command::FAILURE;
    }
}
