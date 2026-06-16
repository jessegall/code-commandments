<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Support\Reporting\ReportLedger;

/**
 * Show the status of prophet reports this project filed, and surface the ones
 * resolved upstream (the cue to update the package and re-judge).
 */
class ReportsCommand extends Command
{
    protected $signature = 'commandments:reports {--check : Quiet hook mode: print only newly-resolved reports}';

    protected $description = 'Show the status of prophet reports this project filed (resolved upstream yet?)';

    public function handle(): int
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
                if ($this->issueState((string) ($report['repo'] ?? ''), (int) ($report['number'] ?? 0)) === 'CLOSED') {
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
                    $report['url'] ?? '',
                ));
            }
        }

        if ($changed) {
            $ledger->write($reports);
        }

        if ($newlyResolved !== []) {
            $this->line('RESOLVED PROPHET REPORTS — reports you filed are now fixed upstream:');

            foreach ($newlyResolved as $report) {
                $this->line(sprintf('  #%d %s — %s', $report['number'] ?? 0, $report['prophet'] ?? '?', $report['url'] ?? ''));
            }

            $this->line('Run `composer update jessegall/code-commandments` and re-run judge — the finding you reported should be gone.');
        }

        return self::SUCCESS;
    }

    private function issueState(string $repo, int $number): ?string
    {
        if ($repo === '' || $number === 0) {
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

        return trim(implode('', $out)) ?: null;
    }
}
