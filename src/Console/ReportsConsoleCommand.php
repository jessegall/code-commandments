<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use Illuminate\Filesystem\Filesystem;
use JesseGall\CodeCommandments\Support\ConfigLoader;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\Reporting\ReportLedger;
use JesseGall\CodeCommandments\Tracking\JsonConfessionTracker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use JesseGall\PhpTypes\T_Int;
use JesseGall\PhpTypes\T_String;

/**
 * Track the prophet reports this project filed and surface the ones that have
 * been resolved upstream — the cue to update the package and re-judge.
 *
 *   reports          List every tracked report with its current state.
 *   reports --check  Quiet mode for hooks: print only reports newly resolved
 *                    since the last check (and mark them notified). Silent
 *                    otherwise, so it's cheap to run on every session start.
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
        $ledger = new ReportLedger(getcwd() ?: '.');
        $reports = $ledger->all();
        $check = (bool) $input->getOption('check');

        if ($reports === []) {
            if (! $check) {
                $output->writeln('No prophet reports filed from this project yet.');
            }

            return Command::SUCCESS;
        }

        $newlyResolved = [];
        $changed = false;

        foreach ($reports as $i => $report) {
            $alreadyDone = ($report['resolved'] ?? false) && ($report['notified'] ?? false);

            if (! ($report['resolved'] ?? false)) {
                $state = $this->issueState(T_String::coalesce($report['repo'] ?? null), T_Int::coalesce($report['number'] ?? null));

                if ($state === 'CLOSED') {
                    $reports[$i]['resolved'] = true;
                    $report['resolved'] = true;
                    $changed = true;
                }
            }

            if (($report['resolved'] ?? false) && ! ($report['notified'] ?? false)) {
                $newlyResolved[] = $report;
                $reports[$i]['notified'] = true;
                $changed = true;
            }

            if (! $check && ! $alreadyDone) {
                $state = ($report['resolved'] ?? false) ? 'RESOLVED' : 'open';
                $output->writeln(sprintf(
                    '  #%d  %-12s %s — %s',
                    $report['number'] ?? 0,
                    $state,
                    $report['prophet'] ?? '?',
                    $report['url'] ?? T_String::empty(),
                ));
            }
        }

        if ($changed) {
            $ledger->write($reports);
        }

        if ($newlyResolved !== []) {
            $output->writeln('RESOLVED PROPHET REPORTS — reports you filed are now fixed upstream:');

            foreach ($newlyResolved as $report) {
                $output->writeln(sprintf('  #%d %s — %s', $report['number'] ?? 0, $report['prophet'] ?? '?', $report['url'] ?? T_String::empty()));
            }

            $output->writeln('Run `composer update jessegall/code-commandments` and re-run judge — the finding you reported should be gone.');
        }

        $this->releaseAnsweredReports($output);

        return Command::SUCCESS;
    }

    /**
     * Lift any report-linked absolution whose upstream issue is now CLOSED, so
     * the finding resurfaces: gone if it was a real false positive (the prophet
     * was fixed), or re-blocking if the issue was closed as wontfix (a genuine
     * sin the agent must now handle). Self-contained — keyed off each reported
     * finding's own issue, independent of the ledger's notify bookkeeping.
     */
    private function releaseAnsweredReports(OutputInterface $output): void
    {
        $tracker = $this->tracker();
        $released = [];

        foreach ($tracker->reportedFindings() as $fingerprint => $entry) {
            $issue = $entry['issue'] ?? null;
            $repo = $entry['repo'] ?? null;

            if ($issue === null || ! is_string($repo) || T_String::isEmpty($repo)) {
                continue;
            }

            if ($this->issueState($repo, (int) $issue) === 'CLOSED') {
                $tracker->releaseReportedFinding($fingerprint);
                $released[(int) $issue] = true;
            }
        }

        if ($released !== []) {
            $issues = implode(', ', array_map(static fn (int $n): string => '#' . $n, array_keys($released)));
            $output->writeln("Report-linked absolution(s) lifted — issue(s) {$issues} answered. Re-run judge: a genuine sin re-blocks; a real false positive is gone after `composer update`.");
        }
    }

    private function tracker(): JsonConfessionTracker
    {
        $basePath = getcwd() ?: '.';
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

    private function issueState(string $repo, int $number): ?string
    {
        if (T_String::isEmpty($repo) || $number === 0) {
            return null;
        }

        exec('command -v gh 2>/dev/null', $probe, $probeCode);

        if ($probeCode !== 0) {
            return null;
        }

        $cmd = 'gh issue view ' . (int) $number
            . ' --repo ' . escapeshellarg($repo)
            . ' --json state -q .state 2>/dev/null';

        exec($cmd, $out, $code);

        if ($code !== 0) {
            return null;
        }

        return trim(implode(T_String::empty(), $out)) ?: null;
    }
}
