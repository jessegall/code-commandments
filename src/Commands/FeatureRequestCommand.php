<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Support\ReportService;
use JesseGall\PhpTypes\T_String;

/**
 * Propose a NEW rule / enhancement as a GitHub issue. Unlike `report` (which targets
 * a specific wrong finding), a feature request has no finding to scope to — so it is
 * the ONE judging action that stays available mid-pilgrimage. Pass the proposal as
 * the argument, or `--stdin` / `--reason-file=<path>` for a long multi-paragraph body.
 */
class FeatureRequestCommand extends Command
{
    protected $signature = 'commandments:feature-request
        {text? : The whole proposal: what to build and why}
        {--stdin : Read the proposal body from STDIN (robust for multi-paragraph text)}
        {--reason-file= : Read the proposal body from a file}
        {--title= : Short issue title; defaults to a summary of the proposal}
        {--proposed-prophet= : Proposed name for the new prophet you are suggesting}
        {--rubric= : Proposed APPLY/LEAVE rubric for the suggested rule}
        {--repo= : GitHub repo (owner/name) to file the issue on}';

    protected $description = 'Propose a NEW rule / enhancement as a GitHub issue (no finding needed; allowed mid-pilgrimage)';

    public function handle(): int
    {
        $reason = $this->resolveReason();

        if ($reason === null || T_String::isBlank($reason)) {
            $this->error('Describe the feature / new rule and why. Pass it as the argument, or with --stdin / --reason-file=<path>.');

            return self::FAILURE;
        }

        return ReportService::fileFeatureRequest(
            [
                'reason' => $reason,
                'title' => $this->option('title'),
                'proposed_prophet' => $this->option('proposed-prophet'),
                'rubric' => $this->option('rubric'),
            ],
            $this->option('repo') ?: config('commandments.report.repo', 'jessegall/code-commandments'),
            $this->info(...),
            $this->warn(...),
        ) === ReportService::SUCCESS ? self::SUCCESS : self::FAILURE;
    }

    private function resolveReason(): ?string
    {
        $file = $this->option('reason-file');

        if (is_string($file) && T_String::isNotBlank($file)) {
            return is_file($file) ? (string) file_get_contents($file) : null;
        }

        if ((bool) $this->option('stdin')) {
            return (string) file_get_contents('php://stdin');
        }

        $text = $this->argument('text');

        return is_string($text) ? $text : null;
    }
}
