<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\CodeCommandments\Contracts\ConfessionTracker;
use JesseGall\CodeCommandments\Support\Reporting\ReportLedger;
use JesseGall\PhpTypes\T_Int;
use JesseGall\PhpTypes\T_String;

/**
 * The shared logic behind `reports` — reconcile the report ledger against GitHub,
 * surface newly-resolved reports, and lift report-linked absolutions whose issue
 * is now closed. One implementation both command variants call; they only differ
 * in how they obtain the tracker and where they print.
 */
final class ReportsService
{
    /**
     * @param  callable(string): void  $emit
     */
    public static function check(ConfessionTracker $tracker, string $basePath, bool $check, callable $emit): void
    {
        $ledger = new ReportLedger($basePath);
        $reports = $ledger->all();

        if ($reports === []) {
            if (! $check) {
                $emit('No prophet reports filed from this project yet.');
            }

            return;
        }

        $newlyResolved = [];
        $changed = false;

        foreach ($reports as $i => $report) {
            $alreadyDone = ($report['resolved'] ?? false) && ($report['notified'] ?? false);

            if (! ($report['resolved'] ?? false)
                && self::issueState(T_String::coalesce($report['repo'] ?? null), T_Int::coalesce($report['number'] ?? null)) === 'CLOSED') {
                $reports[$i]['resolved'] = true;
                $report['resolved'] = true;
                $changed = true;
            }

            if (($report['resolved'] ?? false) && ! ($report['notified'] ?? false)) {
                $newlyResolved[] = $report;
                $reports[$i]['notified'] = true;
                $changed = true;
            }

            if (! $check && ! $alreadyDone) {
                $emit(sprintf(
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
            $emit('RESOLVED PROPHET REPORTS — reports you filed are now fixed upstream:');

            foreach ($newlyResolved as $report) {
                $emit(sprintf('  #%d %s — %s', $report['number'] ?? 0, $report['prophet'] ?? '?', $report['url'] ?? T_String::empty()));
            }

            $emit('Run `composer update jessegall/code-commandments` and re-run judge — the finding you reported should be gone.');
        }

        self::releaseAnsweredReports($tracker, $emit);
    }

    /**
     * Lift any report-linked absolution whose upstream issue is now CLOSED, so the
     * finding resurfaces (gone if a real false positive, re-blocking if wontfix).
     *
     * @param  callable(string): void  $emit
     */
    private static function releaseAnsweredReports(ConfessionTracker $tracker, callable $emit): void
    {
        $released = [];

        foreach ($tracker->reportedFindings() as $fingerprint => $entry) {
            $issue = $entry['issue'] ?? null;
            $repo = $entry['repo'] ?? null;

            if ($issue === null || ! is_string($repo) || T_String::isEmpty($repo)) {
                continue;
            }

            if (self::issueState($repo, (int) $issue) === 'CLOSED') {
                $tracker->releaseReportedFinding($fingerprint);
                $released[(int) $issue] = true;
            }
        }

        if ($released !== []) {
            $issues = implode(', ', array_map(static fn (int $n): string => '#' . $n, array_keys($released)));
            $emit("Report-linked absolution(s) lifted — issue(s) {$issues} answered. Re-run judge: a genuine sin re-blocks; a real false positive is gone after `composer update`.");
        }
    }

    private static function issueState(string $repo, int $number): ?string
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
