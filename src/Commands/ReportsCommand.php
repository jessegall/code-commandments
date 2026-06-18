<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Contracts\ConfessionTracker;
use JesseGall\CodeCommandments\Support\Reporting\ReportLedger;
use JesseGall\PhpTypes\T_Int;
use JesseGall\PhpTypes\T_String;

/**
 * Show the status of prophet reports this project filed, and surface the ones
 * resolved upstream (the cue to update the package and re-judge).
 */
class ReportsCommand extends Command
{
    protected $signature = 'commandments:reports {--check : Quiet hook mode: print only newly-resolved reports}';

    protected $description = 'Show the status of prophet reports this project filed (resolved upstream yet?)';

    public function handle(ConfessionTracker $tracker): int
    {
        $ledger = new ReportLedger(base_path());
        $reports = $ledger->all();
        $check = (bool) $this->option('check');

        if ($reports === []) {
            if (! $check) {
                $this->line('No prophet reports filed from this project yet.');
            }

            return self::SUCCESS;
        }

        $newlyResolved = [];
        $changed = false;

        foreach ($reports as $i => $report) {
            $alreadyDone = ($report['resolved'] ?? false) && ($report['notified'] ?? false);

            if (! ($report['resolved'] ?? false)) {
                if ($this->issueState(T_String::coalesce($report['repo'] ?? null), T_Int::coalesce($report['number'] ?? null)) === 'CLOSED') {
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
                $this->line(sprintf(
                    '  #%d  %-12s %s — %s',
                    $report['number'] ?? 0,
                    ($report['resolved'] ?? false) ? 'RESOLVED' : 'open',
                    $report['prophet'] ?? '?',
                    $report['url'] ?? T_String::empty(),
                ));
            }
        }

        if ($changed) {
            $ledger->write($reports);
        }

        if ($newlyResolved !== []) {
            $this->line('RESOLVED PROPHET REPORTS — reports you filed are now fixed upstream:');

            foreach ($newlyResolved as $report) {
                $this->line(sprintf('  #%d %s — %s', $report['number'] ?? 0, $report['prophet'] ?? '?', $report['url'] ?? T_String::empty()));
            }

            $this->line('Run `composer update jessegall/code-commandments` and re-run judge — the finding you reported should be gone.');
        }

        $this->releaseAnsweredReports($tracker);

        return self::SUCCESS;
    }

    /**
     * Lift any report-linked absolution whose upstream issue is now CLOSED, so
     * the finding resurfaces: gone if it was a real false positive (the prophet
     * was fixed), or re-blocking if the issue was closed as wontfix (a genuine
     * sin the agent must now handle).
     */
    private function releaseAnsweredReports(ConfessionTracker $tracker): void
    {
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
            $this->line("Report-linked absolution(s) lifted — issue(s) {$issues} answered. Re-run judge: a genuine sin re-blocks; a real false positive is gone after `composer update`.");
        }
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

        exec('gh issue view ' . $number . ' --repo ' . escapeshellarg($repo) . ' --json state -q .state 2>/dev/null', $out, $code);

        if ($code !== 0) {
            return null;
        }

        return trim(implode(T_String::empty(), $out)) ?: null;
    }
}
