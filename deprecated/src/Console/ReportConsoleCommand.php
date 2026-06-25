<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Support\ConfigLoader;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\ReportService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use JesseGall\PhpTypes\T_String;

class ReportConsoleCommand extends Command
{
    use BootsStandalone;

    private const DEFAULT_REPO = 'jessegall/code-commandments';

    protected function configure(): void
    {
        $this
            ->setName('report')
            ->setDescription('Report a prophet false-positive / wrong rule as a GitHub issue (to PROPOSE a new rule, use `commandments feature-request`)')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config file')
            ->addOption('prophet', null, InputOption::VALUE_REQUIRED, 'The prophet that misbehaved (name or class)')
            ->addOption('reason', null, InputOption::VALUE_REQUIRED, 'What is wrong (false positive / wrong rule / unclear) — or, with --feature-request, what to build and why')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'File where it was flagged')
            ->addOption('line', null, InputOption::VALUE_REQUIRED, 'Line number')
            ->addOption('fingerprint', null, InputOption::VALUE_REQUIRED, 'The finding fingerprint from `judge --next` — records a report-linked absolution so the finding stays quiet until the issue is answered')
            ->addOption('at', null, InputOption::VALUE_REQUIRED, 'Target the finding by location instead of a fingerprint — path:line (or path:from-to), exactly as judge prints it; records the report-linked absolution and infers --prophet/--file/--line. Combine with --prophet to disambiguate ties')
            ->addOption('feature-request', null, InputOption::VALUE_NONE, 'DEPRECATED — moved to `commandments feature-request "<text>"`. Still works for one release, then removed')
            ->addOption('title', null, InputOption::VALUE_REQUIRED, '(deprecated feature-request) Short issue title; defaults to a summary of --reason')
            ->addOption('proposed-prophet', null, InputOption::VALUE_REQUIRED, '(deprecated feature-request) Proposed name for a new prophet you are suggesting')
            ->addOption('rubric', null, InputOption::VALUE_REQUIRED, '(deprecated feature-request) Proposed APPLY/LEAVE rubric for the suggested rule')
            ->addOption('repo', null, InputOption::VALUE_REQUIRED, 'GitHub repo (owner/name) to file the issue on');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $basePath = Environment::workingDirectory();
        [$registry, $manager, $tracker] = $this->bootEnvironment($input->getOption('config'));

        return ReportService::file(
            $manager,
            $registry,
            $tracker,
            [
                'prophet' => $input->getOption('prophet'),
                'reason' => $input->getOption('reason'),
                'file' => $input->getOption('file'),
                'line' => $input->getOption('line'),
                'fingerprint' => $input->getOption('fingerprint'),
                'at' => $input->getOption('at'),
                'feature_request' => (bool) $input->getOption('feature-request'),
                'title' => $input->getOption('title'),
                'proposed_prophet' => $input->getOption('proposed-prophet'),
                'rubric' => $input->getOption('rubric'),
            ],
            $input->getOption('repo') ?: $this->repoFromConfig($input->getOption('config')),
            $basePath,
            fn (string $line) => $output->writeln('<info>' . $line . '</info>'),
            fn (string $line) => $output->writeln('<comment>' . $line . '</comment>'),
        ) === ReportService::SUCCESS ? Command::SUCCESS : Command::FAILURE;
    }

    private function repoFromConfig(?string $configPath): string
    {
        $basePath = getcwd();
        Environment::setBasePath($basePath);
        $resolved = ConfigLoader::resolve($configPath, $basePath);

        if ($resolved !== null) {
            $repo = ConfigLoader::load($resolved)['report']['repo'] ?? null;

            if (is_string($repo) && T_String::isNotEmpty($repo)) {
                return $repo;
            }
        }

        return self::DEFAULT_REPO;
    }
}
