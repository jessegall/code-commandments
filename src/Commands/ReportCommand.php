<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Contracts\ConfessionTracker;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\ReportService;
use JesseGall\CodeCommandments\Support\ScrollManager;

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
        return \JesseGall\CodeCommandments\Support\ReportService::file(
            $manager,
            $registry,
            $tracker,
            [
                'prophet' => $this->option('prophet'),
                'reason' => $this->option('reason'),
                'file' => $this->option('file'),
                'line' => $this->option('line'),
                'fingerprint' => $this->option('fingerprint'),
                'at' => $this->option('at'),
                'feature_request' => (bool) $this->option('feature-request'),
                'title' => $this->option('title'),
                'proposed_prophet' => $this->option('proposed-prophet'),
                'rubric' => $this->option('rubric'),
            ],
            $this->option('repo') ?: config('commandments.report.repo', 'jessegall/code-commandments'),
            base_path(),
            fn (string $line) => $this->info($line),
            fn (string $line) => $this->warn($line),
        ) === \JesseGall\CodeCommandments\Support\ReportService::SUCCESS ? self::SUCCESS : self::FAILURE;
    }

}
