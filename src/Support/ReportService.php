<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\CodeCommandments\Contracts\ConfessionTracker;
use JesseGall\CodeCommandments\Support\Reporting\IssueReporter;
use JesseGall\CodeCommandments\Support\Reporting\ReportLedger;
use JesseGall\PhpTypes\T_String;

/**
 * The shared logic behind `report` — file a prophet false-positive/wrong-rule as
 * a GitHub issue (resolving the finding from a `--at` locator, recording a
 * report-linked absolution, deduping) OR file a `--feature-request` enhancement.
 * One implementation both command variants call; they only differ in how they
 * obtain the scroll manager / registry / tracker and where they print.
 */
final class ReportService
{
    public const SUCCESS = 0;
    public const FAILURE = 1;

    /**
     * @param  array<string, mixed>  $opts  prophet, reason, file, line, fingerprint, at, repo, feature_request, title, proposed_prophet, rubric
     * @param  callable(string): void  $emit
     * @param  callable(string): void  $error
     */
    public static function file(
        ScrollManager $manager,
        ProphetRegistry $registry,
        ConfessionTracker $tracker,
        array $opts,
        string $defaultRepo,
        string $basePath,
        callable $emit,
        callable $error,
    ): int {
        if ((bool) ($opts['feature_request'] ?? false)) {
            return self::fileFeatureRequest($opts, $defaultRepo, $emit, $error);
        }

        $prophet = $opts['prophet'] ?? null;
        $reason = $opts['reason'] ?? null;
        $file = $opts['file'] ?? null;
        $line = isset($opts['line']) && $opts['line'] !== null ? (int) $opts['line'] : null;
        $fingerprint = is_string($opts['fingerprint'] ?? null) && T_String::isNotBlank($opts['fingerprint']) ? $opts['fingerprint'] : null;
        $snippetPath = is_string($file) ? $file : null;
        $at = $opts['at'] ?? null;

        // --at=path:line[-to]: resolve the locator to the finding, recording the
        // report-linked absolution and inferring --prophet/--file/--line from it.
        if ($fingerprint === null && is_string($at) && T_String::isNotBlank($at)) {
            $loc = Absolver::parseLocator($at);

            if ($loc === null) {
                $error('--at must be path:line or path:from-to (e.g. --at=src/Foo.php:32).');

                return self::FAILURE;
            }

            $filter = is_string($prophet) && $prophet !== '' ? $prophet : null;
            $unique = [];

            foreach ((new Absolver($manager, $registry, $tracker))->findingsAt($loc['path'], $loc['from'], $loc['to'], $filter) as $finding) {
                $unique[$finding->fingerprint] = $finding;
            }

            if ($unique === []) {
                $error("No live finding at {$at}" . ($filter !== null ? " for a prophet matching '{$filter}'" : '') . '. Run judge --next to see current findings.');

                return self::FAILURE;
            }

            if (count($unique) > 1) {
                $error("Multiple findings at {$at} — narrow with --prophet=NAME:");

                foreach ($unique as $finding) {
                    $error("  - {$finding->prophetShort} ({$finding->location()})");
                }

                return self::FAILURE;
            }

            $finding = array_values($unique)[0];
            $fingerprint = $finding->fingerprint;
            $prophet = is_string($prophet) && $prophet !== '' ? $prophet : $finding->prophetShort;
            $file ??= $finding->relativePath;
            $line ??= $finding->line;
            $snippetPath = $finding->filePath;
        }

        if (! is_string($prophet) || T_String::isBlank($prophet) || ! is_string($reason) || T_String::isBlank($reason)) {
            $error('--prophet and --reason are required.');

            return self::FAILURE;
        }

        // Dedup: never file the same finding twice.
        if ($fingerprint !== null && $tracker->isFindingReported($fingerprint)) {
            $existing = $tracker->reportedFindings()[$fingerprint] ?? [];
            $issueRef = isset($existing['issue']) ? "issue #{$existing['issue']}" : 'an existing issue';
            $emit("Already reported as {$issueRef} — not filing a duplicate. The finding stays absolved until that issue is answered.");

            return self::SUCCESS;
        }

        $reporter = new IssueReporter($defaultRepo);
        $issue = $reporter->build($prophet, $file, $line, $reason, self::snippet($snippetPath, $line));
        $result = $reporter->send($issue);

        if (! $result['ok']) {
            $error($result['message']);

            return self::FAILURE;
        }

        $emit($result['message']);

        if (($result['number'] ?? null) !== null && ($result['url'] ?? null) !== null) {
            (new ReportLedger($basePath))->record(
                $result['number'],
                $result['url'],
                $prophet,
                $defaultRepo,
                $reason,
                date('c'),
            );
        }

        if ($fingerprint !== null) {
            $tracker->reportFinding($fingerprint, $reason, $result['number'] ?? null, $defaultRepo);
            $emit('This finding is now absolved until the issue is answered. It survives the post-commit reset; `reports --check` lifts it when the issue closes (a genuine sin then re-blocks).');
        } else {
            $emit('NOTE: no finding locator was given (--at=path:line or --fingerprint), so NO absolution was recorded — this finding still blocks. Re-run with --at=path:line (copy it from judge) to quiet it until the issue is answered.');
        }

        foreach (ReportGuidance::lines($result['number'] ?? null, $defaultRepo) as $guidance) {
            $emit($guidance);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $opts
     * @param  callable(string): void  $emit
     * @param  callable(string): void  $error
     */
    private static function fileFeatureRequest(array $opts, string $repo, callable $emit, callable $error): int
    {
        $reason = $opts['reason'] ?? null;

        if (! is_string($reason) || T_String::isBlank($reason)) {
            $error('--reason is required (describe the feature / new rule and why). --title is recommended.');

            return self::FAILURE;
        }

        $reporter = new IssueReporter($repo);
        $issue = $reporter->buildFeatureRequest(
            $reason,
            $opts['title'] ?? null,
            $opts['proposed_prophet'] ?? null,
            $opts['rubric'] ?? null,
        );
        $result = $reporter->send($issue, 'enhancement');

        if (! $result['ok']) {
            $error($result['message']);

            return self::FAILURE;
        }

        $emit($result['message']);
        $emit('Feature request filed — no absolution recorded (a proposal has no finding to quiet).');

        return self::SUCCESS;
    }

    private static function snippet(?string $file, ?int $line): ?string
    {
        if ($file === null || $line === null || ! is_file($file)) {
            return null;
        }

        $lines = explode(T_String::NEWLINE, (string) file_get_contents($file));

        return $lines[$line - 1] ?? null;
    }
}
