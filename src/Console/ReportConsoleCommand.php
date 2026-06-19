<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Support\Absolver;
use JesseGall\CodeCommandments\Support\ConfigLoader;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\ReportGuidance;
use JesseGall\CodeCommandments\Support\Reporting\IssueReporter;
use JesseGall\CodeCommandments\Support\Reporting\ReportLedger;
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
            ->setDescription('Report a prophet false-positive or wrong rule as a GitHub issue')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config file')
            ->addOption('prophet', null, InputOption::VALUE_REQUIRED, 'The prophet that misbehaved (name or class)')
            ->addOption('reason', null, InputOption::VALUE_REQUIRED, 'What is wrong (false positive / wrong rule / unclear)')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'File where it was flagged')
            ->addOption('line', null, InputOption::VALUE_REQUIRED, 'Line number')
            ->addOption('fingerprint', null, InputOption::VALUE_REQUIRED, 'The finding fingerprint from `judge --next` — records a report-linked absolution so the finding stays quiet until the issue is answered')
            ->addOption('at', null, InputOption::VALUE_REQUIRED, 'Target the finding by location instead of a fingerprint — path:line (or path:from-to), exactly as judge prints it; records the report-linked absolution and infers --prophet/--file/--line. Combine with --prophet to disambiguate ties')
            ->addOption('repo', null, InputOption::VALUE_REQUIRED, 'GitHub repo (owner/name) to file the issue on');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $prophet = $input->getOption('prophet');
        $reason = $input->getOption('reason');
        $file = $input->getOption('file');
        $line = $input->getOption('line') !== null ? (int) $input->getOption('line') : null;
        $fingerprint = is_string($input->getOption('fingerprint')) && T_String::isNotBlank($input->getOption('fingerprint'))
            ? $input->getOption('fingerprint')
            : null;
        $snippetPath = is_string($file) ? $file : null;
        $at = $input->getOption('at');
        $repo = $input->getOption('repo') ?: $this->repoFromConfig($input->getOption('config'));

        [$registry, $manager, $tracker] = $this->bootEnvironment($input->getOption('config'));

        // --at=path:line[-to]: resolve the locator to the finding, recording the
        // report-linked absolution and inferring --prophet/--file/--line from it.
        if ($fingerprint === null && is_string($at) && T_String::isNotBlank($at)) {
            $loc = Absolver::parseLocator($at);

            if ($loc === null) {
                $output->writeln('<error>--at must be path:line or path:from-to (e.g. --at=src/Foo.php:32).</error>');

                return Command::FAILURE;
            }

            $filter = is_string($prophet) && $prophet !== '' ? $prophet : null;
            $unique = [];

            foreach ((new Absolver($manager, $registry, $tracker))->findingsAt($loc['path'], $loc['from'], $loc['to'], $filter) as $finding) {
                $unique[$finding->fingerprint] = $finding;
            }

            if ($unique === []) {
                $output->writeln("<error>No live finding at {$at}" . ($filter !== null ? " for a prophet matching '{$filter}'" : '') . '. Run judge --next to see current findings.</error>');

                return Command::FAILURE;
            }

            if (count($unique) > 1) {
                $output->writeln("<error>Multiple findings at {$at} — narrow with --prophet=NAME:</error>");

                foreach ($unique as $finding) {
                    $output->writeln("  - {$finding->prophetShort} ({$finding->location()})");
                }

                return Command::FAILURE;
            }

            $finding = array_values($unique)[0];
            $fingerprint = $finding->fingerprint;
            $prophet = is_string($prophet) && $prophet !== '' ? $prophet : $finding->prophetShort;
            $file ??= $finding->relativePath;
            $line ??= $finding->line;
            $snippetPath = $finding->filePath;
        }

        if (! is_string($prophet) || T_String::isBlank($prophet) || ! is_string($reason) || T_String::isBlank($reason)) {
            $output->writeln('<error>--prophet and --reason are required.</error>');

            return Command::FAILURE;
        }

        // Dedup: never file the same finding twice. If this fingerprint was
        // already reported, reuse that issue and keep the finding absolved.
        if ($fingerprint !== null && $tracker->isFindingReported($fingerprint)) {
            $existing = $tracker->reportedFindings()[$fingerprint] ?? [];
            $issueRef = isset($existing['issue']) ? "issue #{$existing['issue']}" : 'an existing issue';
            $output->writeln("<info>Already reported as {$issueRef} — not filing a duplicate. The finding stays absolved until that issue is answered.</info>");

            return Command::SUCCESS;
        }

        $reporter = new IssueReporter($repo);
        $issue = $reporter->build($prophet, $file, $line, $reason, $this->snippet($snippetPath, $line));
        $result = $reporter->send($issue);

        if ($result['ok']) {
            $output->writeln('<info>' . $result['message'] . '</info>');

            if (($result['number'] ?? null) !== null && ($result['url'] ?? null) !== null) {
                (new ReportLedger(getcwd() ?: '.'))->record(
                    $result['number'],
                    $result['url'],
                    $prophet,
                    $repo,
                    $reason,
                    date('c'),
                );
            }

            if ($fingerprint !== null) {
                $tracker->reportFinding($fingerprint, $reason, $result['number'] ?? null, $repo);
                $output->writeln('<info>This finding is now absolved until the issue is answered. It survives the post-commit reset; `reports --check` lifts it when the issue closes (a genuine sin then re-blocks).</info>');
            } else {
                $output->writeln('<comment>NOTE: no finding locator was given (--at=path:line or --fingerprint), so NO absolution was recorded — this finding still blocks. Re-run with --at=path:line (copy it from judge) to quiet it until the issue is answered.</comment>');
            }

            foreach (ReportGuidance::lines($result['number'] ?? null, $repo) as $line) {
                $output->writeln($line);
            }

            return Command::SUCCESS;
        }

        $output->writeln('<comment>' . $result['message'] . '</comment>');

        return Command::FAILURE;
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

    private function snippet(?string $file, ?int $line): ?string
    {
        if ($file === null || $line === null || ! is_file($file)) {
            return null;
        }

        $lines = explode(T_String::NEWLINE, (string) file_get_contents($file));

        return $lines[$line - 1] ?? null;
    }
}
