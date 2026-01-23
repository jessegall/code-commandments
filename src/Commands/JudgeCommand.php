<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Contracts\ConfessionTracker;
use JesseGall\CodeCommandments\Support\GitFileDetector;
use JesseGall\CodeCommandments\Support\Output\JudgmentPresenter;
use JesseGall\CodeCommandments\Support\Pipeline;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\ScrollManager;

/**
 * Judge the codebase for sins.
 *
 * The prophets will examine your code and reveal transgressions
 * against the sacred commandments.
 */
class JudgeCommand extends Command
{
    protected $signature = 'commandments:judge
        {--scroll= : Filter by specific scroll (group)}
        {--prophet= : Summon a specific prophet by name}
        {--file= : Judge a specific file}
        {--files= : Judge specific files (comma-separated)}
        {--git : Only judge files that are new or changed in git}
        {--absolve : Mark files as absolved after confession (manual review)}
        {--summary : Show summary output only (for hooks)}
        {--claude : Output optimized for Claude Code AI assistant}';

    protected $description = 'Judge the codebase for sins against the sacred commandments';

    private JudgmentPresenter $presenter;

    private int $totalSins = 0;

    private int $totalWarnings = 0;

    private int $totalFiles = 0;

    /** @var array<string, array<array{prophet: string, message: string, line: int|null}>> */
    private array $manualVerificationFiles = [];

    /** @var array<string, bool> */
    private array $violatedProphets = [];

    /** @var array<string, int> */
    private array $prophetSinCounts = [];

    /** @var array<string, array<string, bool>> */
    private array $prophetFiles = [];

    public function handle(
        ProphetRegistry $registry,
        ScrollManager $manager,
        ConfessionTracker $tracker
    ): int {
        $options = $this->parseOptions();

        $this->presenter = new JudgmentPresenter(
            $this->output,
            $this->output->isVerbose()
        );

        // Handle git mode
        if ($options['gitMode']) {
            $gitFiles = GitFileDetector::for(base_path())->getChangedFiles();

            if (empty($gitFiles)) {
                if (! $options['summaryMode'] && ! $options['claudeMode']) {
                    $this->info('No new or changed files found in git.');
                }

                return self::SUCCESS;
            }

            $options['gitFiles'] = $gitFiles;
        }

        // Show header
        if (! $options['summaryMode'] && ! $options['claudeMode']) {
            $this->presenter->showHeader();
        }

        // Process scrolls
        $scrolls = $options['scrollFilter']
            ? [$options['scrollFilter']]
            : $registry->getScrolls();

        foreach ($scrolls as $scroll) {
            $this->processScroll($scroll, $registry, $manager, $tracker, $options);
        }

        // Show results
        return $this->showResults($options);
    }

    /**
     * Parse command options into an array.
     *
     * @return array<string, mixed>
     */
    private function parseOptions(): array
    {
        return [
            'scrollFilter' => $this->option('scroll'),
            'prophetFilter' => $this->option('prophet'),
            'fileFilter' => $this->option('file'),
            'filesFilter' => $this->option('files')
                ? Pipeline::from(explode(',', $this->option('files')))
                    ->map(fn ($f) => trim($f))
                    ->toArray()
                : [],
            'gitMode' => (bool) $this->option('git'),
            'gitFiles' => [],
            'shouldAbsolve' => (bool) $this->option('absolve'),
            'summaryMode' => (bool) $this->option('summary'),
            'claudeMode' => (bool) $this->option('claude'),
        ];
    }

    /**
     * Process a single scroll.
     *
     * @param  array<string, mixed>  $options
     */
    private function processScroll(
        string $scroll,
        ProphetRegistry $registry,
        ScrollManager $manager,
        ConfessionTracker $tracker,
        array $options
    ): void {
        if (! $registry->hasScroll($scroll)) {
            if (! $options['summaryMode'] && ! $options['claudeMode']) {
                $this->error("Unknown scroll: {$scroll}");
            }

            return;
        }

        if (! $options['summaryMode'] && ! $options['claudeMode']) {
            $this->presenter->showScrollHeader($scroll);
        }

        $results = $this->getResults($scroll, $manager, $options);

        foreach ($results as $filePath => $judgments) {
            $this->processFileJudgments(
                $filePath,
                $judgments,
                $tracker,
                $options
            );
        }

        if (! $options['summaryMode'] && ! $options['claudeMode']) {
            $this->presenter->showScrollSummary($manager->getSummary($results));
        }
    }

    /**
     * Get judgment results based on options.
     *
     * @param  array<string, mixed>  $options
     * @return \Illuminate\Support\Collection
     */
    private function getResults(string $scroll, ScrollManager $manager, array $options)
    {
        if ($options['fileFilter']) {
            $results = $manager->judgeFile($scroll, $options['fileFilter']);

            return collect([$options['fileFilter'] => $results]);
        }

        if (! empty($options['filesFilter'])) {
            return $manager->judgeFiles($scroll, $options['filesFilter']);
        }

        if ($options['gitMode'] && ! empty($options['gitFiles'])) {
            return $manager->judgeFiles($scroll, $options['gitFiles']);
        }

        return $manager->judgeScroll($scroll);
    }

    /**
     * Process judgments for a single file.
     *
     * @param  \Illuminate\Support\Collection  $judgments
     * @param  array<string, mixed>  $options
     */
    private function processFileJudgments(
        string $filePath,
        $judgments,
        ConfessionTracker $tracker,
        array $options
    ): void {
        $relativePath = str_replace(base_path().'/', '', $filePath);
        $fileSins = 0;
        $fileWarnings = 0;

        foreach ($judgments as $prophetClass => $judgment) {
            // Apply prophet filter
            if ($options['prophetFilter']) {
                $shortName = class_basename($prophetClass);
                if (! str_contains(strtolower($shortName), strtolower($options['prophetFilter']))) {
                    continue;
                }
            }

            // Check absolution
            if ($this->isAbsolved($filePath, $prophetClass, $tracker)) {
                continue;
            }

            $prophet = app($prophetClass);

            // Process sins
            foreach ($judgment->sins as $sin) {
                $fileSins++;
                $this->trackSin($prophetClass, $relativePath);

                if (! $options['summaryMode'] && ! $options['claudeMode']) {
                    $this->presenter->showSin($relativePath, $sin);
                }
            }

            // Process warnings
            foreach ($judgment->warnings as $warning) {
                $fileWarnings++;
                $this->violatedProphets[$prophetClass] = true;

                if ($prophet->requiresConfession()) {
                    $this->manualVerificationFiles[$relativePath][] = [
                        'prophet' => class_basename($prophetClass),
                        'message' => $warning->message,
                        'line' => $warning->line,
                    ];
                }

                if (! $options['summaryMode'] && ! $options['claudeMode']) {
                    $this->presenter->showWarning($relativePath, $warning);
                }
            }

            // Handle absolution
            if ($options['shouldAbsolve'] && $judgment->hasWarnings()) {
                $this->absolveFile($filePath, $prophetClass, $tracker, $options);
            }
        }

        $this->totalSins += $fileSins;
        $this->totalWarnings += $fileWarnings;

        if ($fileSins > 0 || $fileWarnings > 0) {
            $this->totalFiles++;
        }
    }

    /**
     * Track a sin for statistics.
     */
    private function trackSin(string $prophetClass, string $relativePath): void
    {
        $this->violatedProphets[$prophetClass] = true;
        $this->prophetSinCounts[$prophetClass] = ($this->prophetSinCounts[$prophetClass] ?? 0) + 1;
        $this->prophetFiles[$prophetClass][$relativePath] = true;
    }

    /**
     * Check if a file is absolved.
     */
    private function isAbsolved(string $filePath, string $prophetClass, ConfessionTracker $tracker): bool
    {
        if (! $tracker->isAbsolved($filePath, $prophetClass)) {
            return false;
        }

        $content = file_get_contents($filePath);

        return $content !== false && ! $tracker->hasChangedSinceAbsolution($filePath, $prophetClass, $content);
    }

    /**
     * Absolve a file.
     *
     * @param  array<string, mixed>  $options
     */
    private function absolveFile(
        string $filePath,
        string $prophetClass,
        ConfessionTracker $tracker,
        array $options
    ): void {
        $content = file_get_contents($filePath);

        if ($content === false) {
            return;
        }

        $tracker->absolve($filePath, $prophetClass, 'Reviewed via commandments:judge --absolve');

        if (! $options['summaryMode'] && ! $options['claudeMode']) {
            $this->output->writeln('    <fg=green>✓ Absolved</>');
        }
    }

    /**
     * Show final results.
     *
     * @param  array<string, mixed>  $options
     */
    private function showResults(array $options): int
    {
        // Show truncation message
        if (! $options['summaryMode'] && ! $options['claudeMode']) {
            $this->presenter->showTruncationMessage($this->totalSins);
        }

        // Show manual verification section
        if (! empty($this->manualVerificationFiles) && ! $options['claudeMode']) {
            $this->presenter->showManualVerificationSection($this->manualVerificationFiles);
        }

        // Show violated prophets details
        if (! empty($this->violatedProphets) && ! $options['summaryMode'] && ! $options['claudeMode']) {
            $this->presenter->showViolatedProphetDetails(array_keys($this->violatedProphets));
        }

        // Claude mode output
        if ($options['claudeMode']) {
            $this->presenter->showClaudeOutput(
                $this->prophetSinCounts,
                $this->prophetFiles,
                $this->totalSins,
                $this->totalFiles,
                $this->totalWarnings,
                $this->manualVerificationFiles
            );

            return $this->totalSins > 0 ? self::FAILURE : self::SUCCESS;
        }

        // No sins found
        if ($this->totalSins === 0 && $this->totalWarnings === 0) {
            $this->presenter->showRighteousBanner();

            return self::SUCCESS;
        }

        // Summary mode
        if ($options['summaryMode']) {
            $this->presenter->showSummaryOutput(
                $this->totalSins,
                $this->totalFiles,
                count($this->manualVerificationFiles)
            );
        } else {
            $this->presenter->showFallenBanner(
                $this->totalSins,
                $this->totalFiles,
                $this->totalWarnings
            );
        }

        return $this->totalSins > 0 ? self::FAILURE : self::SUCCESS;
    }
}
