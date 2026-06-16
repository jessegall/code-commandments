<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Support\Reporting\IssueReporter;

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
        {--repo= : GitHub repo (owner/name) to file the issue on}';

    protected $description = 'Report a prophet false-positive or wrong rule as a GitHub issue';

    public function handle(): int
    {
        $prophet = $this->option('prophet');
        $reason = $this->option('reason');

        if (! is_string($prophet) || trim($prophet) === '' || ! is_string($reason) || trim($reason) === '') {
            $this->error('--prophet and --reason are required.');

            return self::FAILURE;
        }

        $file = $this->option('file');
        $line = $this->option('line') !== null ? (int) $this->option('line') : null;
        $repo = $this->option('repo')
            ?: config('commandments.report.repo', 'jessegall/code-commandments');

        $reporter = new IssueReporter($repo);
        $issue = $reporter->build($prophet, $file, $line, $reason, $this->snippet($file, $line));
        $result = $reporter->send($issue);

        if ($result['ok']) {
            $this->info($result['message']);

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

        $lines = explode("\n", (string) file_get_contents($file));

        return $lines[$line - 1] ?? null;
    }
}
