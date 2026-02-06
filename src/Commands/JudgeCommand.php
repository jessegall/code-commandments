<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Contracts\ConfessionTracker;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Support\GitFileDetector;
use JesseGall\CodeCommandments\Support\Pipeline;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\ScrollManager;

/**
 * Judge the codebase for sins.
 */
class JudgeCommand extends Command
{
    protected $signature = 'commandments:judge
        {--scroll= : Filter by specific scroll (group)}
        {--prophet= : Summon a specific prophet by name}
        {--file= : Judge a specific file}
        {--files= : Judge specific files (comma-separated)}
        {--git : Only judge files that are new or changed in git}
        {--absolve : Mark files as absolved after confession (manual review)}';

    protected $description = 'Judge the codebase for sins against the commandments';

    private int $totalSins = 0;

    private int $totalWarnings = 0;

    private int $totalFiles = 0;

    /** @var array<string, array<array{prophet: string, message: string, line: int|null}>> */
    private array $manualVerificationFiles = [];

    /** @var array<string, int> */
    private array $prophetSinCounts = [];

    /** @var array<string, array<string, array{line: int|null, message: string}>> */
    private array $prophetFileDetails = [];

    public function handle(
        ProphetRegistry $registry,
        ScrollManager $manager,
        ConfessionTracker $tracker
    ): int {
        $scrollFilter = $this->option('scroll');
        $prophetFilter = $this->option('prophet');
        $fileFilter = $this->option('file');
        $filesFilter = $this->option('files')
            ? Pipeline::from(explode(',', $this->option('files')))
                ->map(fn ($f) => trim($f))
                ->toArray()
            : [];
        $gitMode = (bool) $this->option('git');
        $shouldAbsolve = (bool) $this->option('absolve');

        // Handle git mode
        $gitFiles = [];
        if ($gitMode) {
            $gitFiles = GitFileDetector::for(base_path())->getChangedFiles();

            if (empty($gitFiles)) {
                return self::SUCCESS;
            }
        }

        // Process scrolls
        $scrolls = $scrollFilter
            ? [$scrollFilter]
            : $registry->getScrolls();

        foreach ($scrolls as $scroll) {
            if (! $registry->hasScroll($scroll)) {
                continue;
            }

            $results = $this->getResults($scroll, $manager, $fileFilter, $filesFilter, $gitMode, $gitFiles);

            foreach ($results as $filePath => $judgments) {
                $this->processFileJudgments(
                    $filePath,
                    $judgments,
                    $tracker,
                    $prophetFilter,
                    $shouldAbsolve
                );
            }
        }

        return $this->showResults();
    }

    /**
     * Get judgment results based on options.
     *
     * @return \Illuminate\Support\Collection
     */
    private function getResults(
        string $scroll,
        ScrollManager $manager,
        ?string $fileFilter,
        array $filesFilter,
        bool $gitMode,
        array $gitFiles
    ) {
        if ($fileFilter) {
            $results = $manager->judgeFile($scroll, $fileFilter);

            return collect([$fileFilter => $results]);
        }

        if (! empty($filesFilter)) {
            return $manager->judgeFiles($scroll, $filesFilter);
        }

        if ($gitMode && ! empty($gitFiles)) {
            return $manager->judgeFiles($scroll, $gitFiles);
        }

        return $manager->judgeScroll($scroll);
    }

    /**
     * Process judgments for a single file.
     *
     * @param  \Illuminate\Support\Collection  $judgments
     */
    private function processFileJudgments(
        string $filePath,
        $judgments,
        ConfessionTracker $tracker,
        ?string $prophetFilter,
        bool $shouldAbsolve
    ): void {
        $relativePath = str_replace(base_path().'/', '', $filePath);
        $fileSins = 0;
        $fileWarnings = 0;

        foreach ($judgments as $prophetClass => $judgment) {
            // Apply prophet filter
            if ($prophetFilter) {
                $shortName = class_basename($prophetClass);
                if (! str_contains(strtolower($shortName), strtolower($prophetFilter))) {
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
                $this->trackSin($prophetClass, $relativePath, $sin->line, $sin->message);
            }

            // Process warnings (only count those requiring manual review)
            foreach ($judgment->warnings as $warning) {
                if ($prophet->requiresConfession()) {
                    $fileWarnings++;
                    $this->manualVerificationFiles[$relativePath][] = [
                        'prophet' => class_basename($prophetClass),
                        'message' => $warning->message,
                        'line' => $warning->line,
                    ];
                }
            }

            // Handle absolution
            if ($shouldAbsolve && $judgment->hasWarnings()) {
                $content = file_get_contents($filePath);
                if ($content !== false) {
                    $tracker->absolve($filePath, $prophetClass, 'Reviewed via commandments:judge --absolve');
                }
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
    private function trackSin(string $prophetClass, string $relativePath, ?int $line, string $message): void
    {
        $this->prophetSinCounts[$prophetClass] = ($this->prophetSinCounts[$prophetClass] ?? 0) + 1;
        $this->prophetFileDetails[$prophetClass][$relativePath][] = [
            'line' => $line,
            'message' => $message,
        ];
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
     * Show final results.
     */
    private function showResults(): int
    {
        if ($this->totalSins === 0 && $this->totalWarnings === 0) {
            $this->output->writeln('Righteous: No sins found.');

            return self::SUCCESS;
        }

        // Show sins
        if ($this->totalSins > 0) {
            $this->output->writeln("SINS: {$this->totalSins} in {$this->totalFiles} files");
            $this->output->newLine();
            $this->output->writeln('DO NOT COMMIT: Fix all sins before committing.');
            $this->output->newLine();

            // Sort by sin count descending
            arsort($this->prophetSinCounts);

            foreach ($this->prophetSinCounts as $prophetClass => $count) {
                $shortName = class_basename($prophetClass);
                $filterName = str_replace('Prophet', '', $shortName);
                $prophet = app($prophetClass);
                $autoFixable = $prophet instanceof SinRepenter ? ' [AUTO-FIXABLE]' : '';

                $this->output->writeln("{$shortName} ({$count}){$autoFixable}");
                $this->output->writeln("  {$prophet->description()}");
                $this->output->writeln("  Details: php artisan commandments:scripture --prophet={$filterName}");
                $this->output->newLine();

                // Show file:line details
                foreach ($this->prophetFileDetails[$prophetClass] ?? [] as $file => $sins) {
                    foreach ($sins as $sin) {
                        $line = $sin['line'] ? ":{$sin['line']}" : '';
                        $this->output->writeln("  {$file}{$line}");
                        $this->output->writeln("    {$sin['message']}");
                    }
                }

                $this->output->newLine();
            }

            $this->output->writeln('Auto-fix: php artisan commandments:repent');
        }

        // Show warnings
        if ($this->totalWarnings > 0 && ! empty($this->manualVerificationFiles)) {
            $this->output->newLine();
            $this->output->writeln("WARNINGS: {$this->totalWarnings} requiring manual review");
            $this->output->newLine();

            foreach ($this->manualVerificationFiles as $file => $issues) {
                $this->output->writeln($file);
                foreach ($issues as $issue) {
                    $line = $issue['line'] ? ":{$issue['line']}" : '';
                    $this->output->writeln("  [{$issue['prophet']}]{$line} {$issue['message']}");
                }
            }
        }

        return $this->totalSins > 0 ? self::FAILURE : self::SUCCESS;
    }
}
