<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Output;

use Illuminate\Console\OutputStyle;
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
        array $manualVerificationFiles = []
    ): void {
        if ($totalSins === 0 && $totalWarnings === 0) {
            $this->output->writeln('No sins found. The code is righteous.');

            return;
        }

        // Show sins section
        if ($totalSins > 0) {
            $this->output->writeln("SINS FOUND: {$totalSins} total across {$totalFiles} files");
            $this->output->newLine();
            $this->output->writeln('SUMMARY BY TYPE:');

            // Sort by sin count descending
            arsort($prophetSinCounts);

            foreach ($prophetSinCounts as $prophetClass => $count) {
                $shortName = class_basename($prophetClass);
                $fileCount = count($prophetFiles[$prophetClass] ?? []);
                $prophet = app($prophetClass);
                $this->output->writeln("  - {$shortName}: {$count} sins in {$fileCount} files");
                $this->output->writeln("    {$prophet->description()}");
            }

            $this->output->newLine();
            $this->output->writeln('TO FIX: Run the following commands to see affected files and details for each sin type:');
            $this->output->newLine();

            foreach (array_keys($prophetSinCounts) as $prophetClass) {
                $shortName = class_basename($prophetClass);
                $filterName = str_replace('Prophet', '', $shortName);
                $this->output->writeln("  php artisan commandments:judge --prophet={$filterName}");
            }

            $this->output->newLine();
            $this->output->writeln('TO AUTO-FIX: Run `php artisan commandments:repent` to automatically fix sins where possible.');
        }

        // Show warnings section
        if ($totalWarnings > 0 && ! empty($manualVerificationFiles)) {
            $this->output->newLine();
            $this->output->writeln('---');
            $this->output->newLine();
            $this->output->writeln("WARNINGS REQUIRING MANUAL REVIEW: {$totalWarnings} total");
            $this->output->newLine();
            $this->output->writeln('IMPORTANT: Warnings are potential issues that require your manual review.');
            $this->output->writeln('You must examine each warning and determine if it is an actual sin that needs fixing.');
            $this->output->writeln('Some warnings may be false positives depending on the context.');
            $this->output->newLine();
            $this->output->writeln('FILES TO REVIEW:');

            foreach ($manualVerificationFiles as $file => $issues) {
                $this->output->writeln("  {$file}");
                foreach ($issues as $issue) {
                    $line = $issue['line'] ? ":{$issue['line']}" : '';
                    $this->output->writeln("    [{$issue['prophet']}]{$line} {$issue['message']}");
                }
            }

            $this->output->newLine();
            $this->output->writeln('After reviewing, use --absolve flag to mark files as reviewed.');
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
