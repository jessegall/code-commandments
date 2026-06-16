<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Support\Reporting\ReportLedger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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
                $state = $this->issueState((string) ($report['repo'] ?? ''), (int) ($report['number'] ?? 0));

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
                    $report['url'] ?? '',
                ));
            }
        }

        if ($changed) {
            $ledger->write($reports);
        }

        if ($newlyResolved !== []) {
            $output->writeln('RESOLVED PROPHET REPORTS — reports you filed are now fixed upstream:');

            foreach ($newlyResolved as $report) {
                $output->writeln(sprintf('  #%d %s — %s', $report['number'] ?? 0, $report['prophet'] ?? '?', $report['url'] ?? ''));
            }

            $output->writeln('Run `composer update jessegall/code-commandments` and re-run judge — the finding you reported should be gone.');
        }

        return Command::SUCCESS;
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

        $cmd = 'gh issue view ' . (int) $number
            . ' --repo ' . escapeshellarg($repo)
            . ' --json state -q .state 2>/dev/null';

        exec($cmd, $out, $code);

        if ($code !== 0) {
            return null;
        }

        return trim(implode('', $out)) ?: null;
    }
}
