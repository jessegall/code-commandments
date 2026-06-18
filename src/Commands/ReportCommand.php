<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Contracts\ConfessionTracker;
use JesseGall\CodeCommandments\Support\Reporting\IssueReporter;
use JesseGall\CodeCommandments\Support\Reporting\ReportLedger;
use JesseGall\PhpTypes\T_String;

/**
 * Report a prophet false-positive or wrong rule as a GitHub issue.
 */
class ReportCommand extends Command
{
    protected $signature = 'commandments:report
        {--prophet= : The prophet that misbehaved (name or class)}
        {--reason= : What is wrong (false positive / wrong rule / unclear)}
        {--file= : File where it was flagged}
        {--line= : Line number}
        {--fingerprint= : The finding fingerprint from `judge --next` — records a report-linked absolution so the finding stays quiet until the issue is answered}
        {--repo= : GitHub repo (owner/name) to file the issue on}';

    protected $description = 'Report a prophet false-positive or wrong rule as a GitHub issue';

    public function handle(ConfessionTracker $tracker): int
    {
        $prophet = $this->option('prophet');
        $reason = $this->option('reason');

        if (! is_string($prophet) || T_String::isBlank($prophet) || ! is_string($reason) || T_String::isBlank($reason)) {
            $this->error('--prophet and --reason are required.');

            return self::FAILURE;
        }

        $file = $this->option('file');
        $line = $this->option('line') !== null ? (int) $this->option('line') : null;
        $fingerprint = is_string($this->option('fingerprint')) && T_String::isNotBlank($this->option('fingerprint'))
            ? $this->option('fingerprint')
            : null;
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
        $issue = $reporter->build($prophet, $file, $line, $reason, $this->snippet($file, $line));
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
            }

            foreach (\JesseGall\CodeCommandments\Support\ReportGuidance::lines($result['number'] ?? null, $repo) as $line) {
                $this->line($line);
            }

            return self::SUCCESS;
        }

        $this->warn($result['message']);

        return self::FAILURE;
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
