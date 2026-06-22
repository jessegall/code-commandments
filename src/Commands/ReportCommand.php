<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Contracts\ConfessionTracker;
use JesseGall\CodeCommandments\Support\Absolver;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\Reporting\IssueReporter;
use JesseGall\CodeCommandments\Support\Reporting\ReportLedger;
use JesseGall\CodeCommandments\Support\ScrollManager;
use JesseGall\PhpTypes\T_String;

/**
 * Report a prophet false-positive or wrong rule as a GitHub issue.
 */
class ReportCommand extends Command
{
    protected $signature = 'commandments:report
        {--prophet= : The prophet that misbehaved (name or class)}
        {--reason= : What is wrong (false positive / wrong rule / unclear) — or, with --feature-request, what to build and why}
        {--file= : File where it was flagged}
        {--line= : Line number}
        {--fingerprint= : The finding fingerprint from `judge --next` — records a report-linked absolution so the finding stays quiet until the issue is answered}
        {--at= : Target the finding by location instead of a fingerprint — path:line (or path:from-to), exactly as judge prints it; records the report-linked absolution and infers --prophet/--file/--line. Combine with --prophet to disambiguate ties}
        {--feature-request : File an ENHANCEMENT / new-rule proposal instead of a false-positive report — needs no --prophet/--at/--fingerprint, records no absolution}
        {--title= : (feature-request) Short issue title; defaults to a summary of --reason}
        {--proposed-prophet= : (feature-request) Proposed name for a new prophet you are suggesting}
        {--rubric= : (feature-request) Proposed APPLY/LEAVE rubric for the suggested rule}
        {--repo= : GitHub repo (owner/name) to file the issue on}';

    protected $description = 'Report a prophet false-positive/wrong rule, or (with --feature-request) file a new-prophet/feature proposal, as a GitHub issue';

    public function handle(ConfessionTracker $tracker, ProphetRegistry $registry, ScrollManager $manager): int
    {
        if ($this->option('feature-request')) {
            return $this->handleFeatureRequest();
        }

        $prophet = $this->option('prophet');
        $reason = $this->option('reason');
        $file = $this->option('file');
        $line = $this->option('line') !== null ? (int) $this->option('line') : null;
        $fingerprint = is_string($this->option('fingerprint')) && T_String::isNotBlank($this->option('fingerprint'))
            ? $this->option('fingerprint')
            : null;
        $snippetPath = is_string($file) ? $file : null;
        $at = $this->option('at');

        // --at=path:line[-to]: resolve the locator to the finding, recording the
        // report-linked absolution and inferring --prophet/--file/--line from it.
        if ($fingerprint === null && is_string($at) && T_String::isNotBlank($at)) {
            $loc = Absolver::parseLocator($at);

            if ($loc === null) {
                $this->error('--at must be path:line or path:from-to (e.g. --at=src/Foo.php:32).');

                return self::FAILURE;
            }

            $filter = is_string($prophet) && $prophet !== '' ? $prophet : null;
            $unique = [];

            foreach ((new Absolver($manager, $registry, $tracker))->findingsAt($loc['path'], $loc['from'], $loc['to'], $filter) as $finding) {
                $unique[$finding->fingerprint] = $finding;
            }

            if ($unique === []) {
                $this->error("No live finding at {$at}" . ($filter !== null ? " for a prophet matching '{$filter}'" : '') . '. Run judge --next to see current findings.');

                return self::FAILURE;
            }

            if (count($unique) > 1) {
                $this->error("Multiple findings at {$at} — narrow with --prophet=NAME:");

                foreach ($unique as $finding) {
                    $this->line("  - {$finding->prophetShort} ({$finding->location()})");
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
            $this->error('--prophet and --reason are required.');

            return self::FAILURE;
        }

        $repo = $this->option('repo')
            ?: config('commandments.report.repo', 'jessegall/code-commandments');

        // Dedup: never file the same finding twice. If this fingerprint was
        // already reported, reuse that issue and keep the finding absolved.
        if ($fingerprint !== null && $tracker->isFindingReported($fingerprint)) {
            $existing = $tracker->reportedFindings()[$fingerprint] ?? [];
            $issueRef = isset($existing['issue']) ? "issue #{$existing['issue']}" : 'an existing issue';
            $this->info("Already reported as {$issueRef} — not filing a duplicate. The finding stays absolved until that issue is answered.");

            return self::SUCCESS;
        }

        $reporter = new IssueReporter($repo);
        $issue = $reporter->build($prophet, $file, $line, $reason, $this->snippet($snippetPath, $line));
        $result = $reporter->send($issue);

        if ($result['ok']) {
            $this->info($result['message']);

            if (($result['number'] ?? null) !== null && ($result['url'] ?? null) !== null) {
                (new ReportLedger(base_path()))->record(
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
                $this->info('This finding is now absolved until the issue is answered. It survives the post-commit reset; `reports --check` lifts it when the issue closes (a genuine sin then re-blocks).');
            } else {
                $this->warn('NOTE: no finding locator was given (--at=path:line or --fingerprint), so NO absolution was recorded — this finding still blocks. Re-run with --at=path:line (copy it from judge) to quiet it until the issue is answered.');
            }

            foreach (\JesseGall\CodeCommandments\Support\ReportGuidance::lines($result['number'] ?? null, $repo) as $line) {
                $this->line($line);
            }

            return self::SUCCESS;
        }

        $this->warn($result['message']);

        return self::FAILURE;
    }

    /**
     * File an enhancement / new-rule proposal: no finding, no absolution, an
     * `enhancement`-labelled issue.
     */
    private function handleFeatureRequest(): int
    {
        $reason = $this->option('reason');

        if (! is_string($reason) || T_String::isBlank($reason)) {
            $this->error('--reason is required (describe the feature / new rule and why). --title is recommended.');

            return self::FAILURE;
        }

        $repo = $this->option('repo')
            ?: config('commandments.report.repo', 'jessegall/code-commandments');

        $reporter = new IssueReporter($repo);
        $issue = $reporter->buildFeatureRequest(
            $reason,
            $this->option('title'),
            $this->option('proposed-prophet'),
            $this->option('rubric'),
        );
        $result = $reporter->send($issue, 'enhancement');

        if (! $result['ok']) {
            $this->warn($result['message']);

            return self::FAILURE;
        }

        $this->info($result['message']);
        $this->line('Feature request filed — no absolution recorded (a proposal has no finding to quiet).');

        return self::SUCCESS;
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
