<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Output;

use Illuminate\Console\OutputStyle;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Sin;
use JesseGall\CodeCommandments\Results\Warning;
use JesseGall\CodeCommandments\Support\Pipeline;

/**
 * Handles presentation/output of judgment results.
 */
final class JudgmentPresenter
{
    private int $displayedSins = 0;

    private int $maxDisplayedSins = 10;

    public function __construct(
        private OutputStyle $output,
        private bool $verbose = false,
    ) {}

    /**
     * Set the maximum number of sins to display.
     */
    public function setMaxDisplayedSins(int $max): self
    {
        $this->maxDisplayedSins = $max;

        return $this;
    }

    /**
     * Display the header banner.
     */
    public function showHeader(): void
    {
        $this->output->writeln('<fg=yellow>');
        $this->output->writeln('  ╔═══════════════════════════════════════════════════════════╗');
        $this->output->writeln('  ║          THE PROPHETS HAVE BEEN SUMMONED                  ║');
        $this->output->writeln('  ║       Let thy code be judged by the commandments          ║');
        $this->output->writeln('  ╚═══════════════════════════════════════════════════════════╝');
        $this->output->writeln('</>');
        $this->output->newLine();
    }

    /**
     * Display a scroll header.
     */
    public function showScrollHeader(string $scroll): void
    {
        $this->output->writeln("📜 Examining the scroll of <fg=cyan>{$scroll}</>");
        $this->output->newLine();
    }

    /**
     * Display a sin.
     */
    public function showSin(string $filePath, Sin $sin): bool
    {
        if (! $this->verbose && $this->displayedSins >= $this->maxDisplayedSins) {
            return false;
        }

        $line = $sin->line ? ":{$sin->line}" : '';
        $this->output->writeln("  <fg=red>✗</> <fg=white>{$filePath}{$line}</>");
        $this->output->writeln("    <fg=red>{$sin->message}</>");

        if ($sin->suggestion) {
            $this->output->writeln("    <fg=gray>→ {$sin->suggestion}</>");
        }

        $this->displayedSins++;

        return true;
    }

    /**
     * Display a warning.
     */
    public function showWarning(string $filePath, Warning $warning): void
    {
        $line = $warning->line ? ":{$warning->line}" : '';
        $this->output->writeln("  <fg=yellow>⚠</> <fg=white>{$filePath}{$line}</>");
        $this->output->writeln("    <fg=yellow>{$warning->message}</>");
    }

    /**
     * Display truncation message.
     */
    public function showTruncationMessage(int $totalSins): void
    {
        if (! $this->verbose && $totalSins > $this->maxDisplayedSins) {
            $remaining = $totalSins - $this->maxDisplayedSins;
            $this->output->newLine();
            $this->output->writeln("  <fg=yellow>... and {$remaining} more sins. Run with -v to see all.</>");
        }
    }

    /**
     * Display scroll summary.
     *
     * @param  array{files: int, righteous: int, fallen: int}  $summary
     */
    public function showScrollSummary(array $summary): void
    {
        $this->output->newLine();
        $this->output->writeln("  <fg=gray>Files examined: {$summary['files']}, Righteous: {$summary['righteous']}, Fallen: {$summary['fallen']}</>");
        $this->output->newLine();
    }

    /**
     * Display the righteous (no sins) banner.
     */
    public function showRighteousBanner(): void
    {
        $this->output->newLine();
        $this->output->writeln('<fg=green>');
        $this->output->writeln('  ╔═══════════════════════════════════════════════════════════╗');
        $this->output->writeln('  ║                   THY CODE IS RIGHTEOUS                   ║');
        $this->output->writeln('  ║            The prophets find no transgressions            ║');
        $this->output->writeln('  ╚═══════════════════════════════════════════════════════════╝');
        $this->output->writeln('</>');
    }

    /**
     * Display the fallen (sins found) banner.
     */
    public function showFallenBanner(int $totalSins, int $totalFiles, int $totalWarnings = 0): void
    {
        $this->output->newLine();
        $this->output->writeln('<fg=red>');
        $this->output->writeln('  ╔═══════════════════════════════════════════════════════════╗');
        $this->output->writeln('  ║  '.$totalSins.' SINS found across '.$totalFiles.' files'.str_repeat(' ', max(0, 35 - strlen((string) $totalSins) - strlen((string) $totalFiles))).'║');

        if ($totalWarnings > 0) {
            $this->output->writeln('  ║  '.$totalWarnings.' WARNINGS requiring review'.str_repeat(' ', max(0, 34 - strlen((string) $totalWarnings))).'║');
        }

        $this->output->writeln('  ║                                                             ║');
        $this->output->writeln('  ║      Run "php artisan commandments:repent" for absolution   ║');
        $this->output->writeln('  ╚═══════════════════════════════════════════════════════════╝');
        $this->output->writeln('</>');
    }

    /**
     * Display summary output for hooks.
     */
    public function showSummaryOutput(int $totalSins, int $totalFiles, int $manualVerificationCount): void
    {
        if ($totalSins > 0) {
            $this->output->writeln("<fg=red>⛔ {$totalSins} sins found in {$totalFiles} files. Run 'php artisan commandments:judge' for details.</>");
        }

        if ($manualVerificationCount > 0) {
            $this->output->writeln("<fg=magenta>📋 {$manualVerificationCount} files need manual verification.</>");
        }
    }

    /**
     * Display Claude-optimized output.
     *
     * @param  array<string, int>  $prophetSinCounts
     * @param  array<string, array<string, bool>>  $prophetFiles
     * @param  array<string, array<array{prophet: string, message: string, line: int|null}>>  $manualVerificationFiles
     */
    public function showClaudeOutput(
        array $prophetSinCounts,
        array $prophetFiles,
        int $totalSins,
        int $totalFiles,
        int $totalWarnings = 0,
        array $manualVerificationFiles = [],
        bool $gitMode = false,
    ): void {
        if ($totalSins === 0 && $totalWarnings === 0) {
            $this->output->writeln('Righteous: No sins found.');

            return;
        }

        $gitFlag = $gitMode ? ' --git' : '';

        // Show sins section
        if ($totalSins > 0) {
            $this->output->writeln("SINS: {$totalSins} in {$totalFiles} files");
            $this->output->newLine();
            $this->output->writeln('DO NOT COMMIT: Fix all sins before committing.');
            $this->output->newLine();

            arsort($prophetSinCounts);

            foreach ($prophetSinCounts as $prophetClass => $count) {
                $shortName = class_basename($prophetClass);
                $prophet = new $prophetClass();
                $autoFixable = $prophet instanceof SinRepenter ? ' [AUTO-FIXABLE]' : '';

                $this->output->writeln("- {$shortName} ({$count}){$autoFixable}");
            }

            $this->output->newLine();
            $this->output->writeln('FIX EACH SIN TYPE: Process one at a time, in order:');
            $this->output->writeln("  1. Read the rule:    php artisan commandments:scripture --prophet=NAME");
            $this->output->writeln("  2. See the files:    php artisan commandments:judge --prophet=NAME{$gitFlag}");
            $this->output->writeln('  3. Fix all violations following the detailed description exactly');
            $this->output->writeln('  4. Move to the next sin type');

            $hasAutoFixable = false;
            foreach ($prophetSinCounts as $prophetClass => $count) {
                if (new $prophetClass() instanceof SinRepenter) {
                    $hasAutoFixable = true;
                    break;
                }
            }

            if ($hasAutoFixable) {
                $this->output->newLine();
                $this->output->writeln("[AUTO-FIXABLE] sins can be fixed with: php artisan commandments:repent{$gitFlag}");
            }
        }

        // Show warnings section
        if ($totalWarnings > 0 && ! empty($manualVerificationFiles)) {
            $this->output->newLine();
            $this->output->writeln("WARNINGS: {$totalWarnings} requiring manual review");
            $this->output->newLine();

            $warningProphets = [];
            foreach ($manualVerificationFiles as $file => $issues) {
                foreach ($issues as $issue) {
                    $filterName = str_replace('Prophet', '', $issue['prophet']);
                    $warningProphets[$filterName] = ($warningProphets[$filterName] ?? 0) + 1;
                }
            }

            foreach ($warningProphets as $filterName => $count) {
                $this->output->writeln("- {$filterName}Prophet ({$count})");
            }

            $this->output->newLine();
            $this->output->writeln('REVIEW EACH WARNING TYPE: Process one at a time:');
            $this->output->writeln("  1. Read the rule:    php artisan commandments:scripture --prophet=NAME");
            $this->output->writeln("  2. See the files:    php artisan commandments:judge --prophet=NAME{$gitFlag}");
            $this->output->writeln('  3. Review and fix following the detailed description exactly');
            $this->output->newLine();
            $this->output->writeln('NOTE: Warnings in files you just created or edited MUST be investigated.');
            $this->output->writeln('Warnings in files you did not touch can be ignored.');
        }
    }

    /**
     * Display manual verification section.
     *
     * @param  array<string, array<array{prophet: string, message: string, line: int|null}>>  $files
     */
    public function showManualVerificationSection(array $files): void
    {
        if (empty($files)) {
            return;
        }

        $this->output->newLine();
        $this->output->writeln('<fg=magenta>');
        $this->output->writeln('  ┌───────────────────────────────────────────────────────────┐');
        $this->output->writeln('  │         📋 FILES REQUIRING MANUAL VERIFICATION            │');
        $this->output->writeln('  └───────────────────────────────────────────────────────────┘');
        $this->output->writeln('</>');
        $this->output->newLine();

        foreach ($files as $file => $issues) {
            $this->output->writeln("  <fg=white>{$file}</>");
            foreach ($issues as $issue) {
                $line = $issue['line'] ? ":{$issue['line']}" : '';
                $this->output->writeln("    <fg=magenta>→ [{$issue['prophet']}]{$line}</> {$issue['message']}");
            }
            $this->output->newLine();
        }

        $this->output->writeln('  <fg=gray>Review these files manually and run with --absolve to mark as reviewed.</>');
        $this->output->newLine();
    }

    /**
     * Display violated prophet details.
     *
     * @param  array<string>  $prophetClasses
     */
    public function showViolatedProphetDetails(array $prophetClasses): void
    {
        $this->output->newLine();
        $this->output->writeln('<fg=cyan>');
        $this->output->writeln('  ┌───────────────────────────────────────────────────────────┐');
        $this->output->writeln('  │              📖 COMMANDMENT DETAILS                       │');
        $this->output->writeln('  └───────────────────────────────────────────────────────────┘');
        $this->output->writeln('</>');

        foreach ($prophetClasses as $prophetClass) {
            $prophet = app($prophetClass);
            $shortName = class_basename($prophetClass);

            $this->output->newLine();
            $this->output->writeln("  <fg=cyan;options=bold>{$shortName}</>");
            $this->output->writeln("  <fg=white>{$prophet->description()}</>");
            $this->output->newLine();

            $detailed = $prophet->detailedDescription();
            $lines = explode("\n", $detailed);
            foreach ($lines as $line) {
                $this->output->writeln("  <fg=gray>{$line}</>");
            }
        }
    }

    /**
     * Reset the displayed sins counter.
     */
    public function reset(): void
    {
        $this->displayedSins = 0;
    }
}
