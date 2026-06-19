<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Contracts\ConfessionTracker;
use JesseGall\CodeCommandments\Contracts\ParameterizedRepenter;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\FindingCollector;
use JesseGall\CodeCommandments\Support\FindingQueue;
use JesseGall\CodeCommandments\Support\Fingerprint;
use JesseGall\CodeCommandments\Support\RootCauseResolver;
use JesseGall\CodeCommandments\Support\GitFileDetector;
use JesseGall\CodeCommandments\Support\Output\NextFindingPresenter;
use JesseGall\CodeCommandments\Support\Pipeline;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\ScrollManager;
use JesseGall\PhpTypes\T_String;

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
        {--path= : Override the scroll path and target a specific directory (bypasses all excludes)}
        {--git : Only judge files that are new or changed in git}
        {--staged : Only judge files staged for commit (what the pre-commit gate uses)}
        {--absolve : Mark files as absolved after confession (manual review)}
        {--no-cache : Force a fresh judge — never read the findings cache (the pre-commit gate uses this to stay authoritative)}
        {--next : Show exactly one finding at a time (fix or absolve to advance)}';

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

    /** @var array<string, bool> prophetClass => any finding actually auto-fixable */
    private array $prophetAutoFixable = [];

    public function handle(
        ProphetRegistry $registry,
        ScrollManager $manager,
        ConfessionTracker $tracker
    ): int {
        if ((bool) $this->option('no-cache')) {
            $manager->setUseCache(false);
        }

        $scrollFilter = $this->option('scroll');
        $prophetFilter = $this->option('prophet');

        // A `--prophet` filter narrows which prophets actually RUN, not just what
        // is reported — so an unrelated prophet is never invoked.
        $manager->setProphetFilter($prophetFilter);

        $fileFilter = $this->option('file');
        $filesFilter = $this->option('files')
            ? Pipeline::from(explode(',', $this->option('files')))
                ->map(fn ($f) => trim($f))
                ->toArray()
            : [];
        $gitMode = (bool) $this->option('git');
        $stagedMode = (bool) $this->option('staged');
        $pathFilter = $this->option('path');
        $shouldAbsolve = (bool) $this->option('absolve');

        $exclusiveFlags = array_filter([
            '--file' => $fileFilter !== null,
            '--files' => ! empty($filesFilter),
            '--git' => $gitMode,
            '--staged' => $stagedMode,
            '--path' => $pathFilter !== null,
        ]);

        if (count($exclusiveFlags) > 1) {
            $this->error('--file, --files, --git, --staged, and --path are mutually exclusive.');

            return self::FAILURE;
        }

        if ($pathFilter !== null) {
            $resolvedPath = realpath($pathFilter);

            if ($resolvedPath === false || ! is_dir($resolvedPath)) {
                $this->error("--path does not point to an existing directory: {$pathFilter}");

                return self::FAILURE;
            }

            $pathFilter = $resolvedPath;
        }

        // --staged reuses the git file-list routing, but with only the files
        // staged for commit — this is what the pre-commit gate judges.
        if ($stagedMode) {
            $gitMode = true;
        }

        // Handle git mode
        $gitFiles = [];
        if ($gitMode) {
            $detector = GitFileDetector::for(Environment::basePath());
            $gitFiles = $stagedMode ? $detector->getStagedFiles() : $detector->getChangedFiles();

            if (empty($gitFiles)) {
                return self::SUCCESS;
            }
        }

        // Process scrolls
        $scrolls = $scrollFilter
            ? [$scrollFilter]
            : $registry->getScrolls();

        $fullScan = $fileFilter === null
            && empty($filesFilter)
            && ! $gitMode
            && $pathFilter === null
            && $prophetFilter === null;

        if ((bool) $this->option('next')) {
            return $this->runNext(
                $registry,
                $manager,
                $tracker,
                $scrolls,
                $fileFilter,
                $filesFilter,
                $gitMode,
                $gitFiles,
                $pathFilter,
                $prophetFilter,
                $fullScan,
            );
        }

        foreach ($scrolls as $scroll) {
            if (! $registry->hasScroll($scroll)) {
                continue;
            }

            $results = $this->getResults($scroll, $manager, $fileFilter, $filesFilter, $gitMode, $gitFiles, $pathFilter);

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

        if ($fullScan) {
            $tracker->gcUnseenFindings();
        }

        return $this->showResults($prophetFilter, $gitMode, $stagedMode);
    }

    /**
     * @param  array<string>  $scrolls
     * @param  array<string>  $filesFilter
     * @param  array<string>  $gitFiles
     */
    private function runNext(
        ProphetRegistry $registry,
        ScrollManager $manager,
        ConfessionTracker $tracker,
        array $scrolls,
        ?string $fileFilter,
        array $filesFilter,
        bool $gitMode,
        array $gitFiles,
        ?string $pathFilter,
        ?string $prophetFilter,
        bool $fullScan,
    ): int {
        $collector = new FindingCollector($tracker);
        $findings = [];

        foreach ($scrolls as $scroll) {
            if (! $registry->hasScroll($scroll)) {
                continue;
            }

            $results = $this->getResults($scroll, $manager, $fileFilter, $filesFilter, $gitMode, $gitFiles, $pathFilter);
            $findings = array_merge($findings, $collector->collect($results, $prophetFilter, markSeen: true));
        }

        if ($fullScan) {
            $tracker->gcUnseenFindings();
        }

        $ordered = FindingQueue::order($findings);

        if ($ordered === []) {
            $this->output->writeln(NextFindingPresenter::clearLine());

            return self::SUCCESS;
        }

        // Lazily annotate ONLY the finding about to be presented with its
        // root-cause hint — so a filtered run surfaces "fix the cause first"
        // even though the cause prophet didn't run, at zero cost to the rest.
        $activeProphets = [];
        foreach ($scrolls as $scroll) {
            if ($registry->hasScroll($scroll)) {
                $activeProphets += $manager->activeProphetClasses($scroll);
            }
        }

        $resolver = new RootCauseResolver(
            fn (string $filePath): ?CodebaseIndex => $manager->codebaseIndexForFile($filePath),
        );

        $finding = $resolver->annotate($ordered[0], $activeProphets);
        $prophet = new $finding->prophetClass();
        $absolvable = $finding->isWarning() || $prophet->requiresConfession();
        // Per-finding flag: a SinRepenter prophet may emit non-fixable findings.
        $autoFixable = $finding->autoFixable;
        $repentInputs = ($autoFixable && $prophet instanceof ParameterizedRepenter) ? $prophet->repentInputs() : null;

        foreach (NextFindingPresenter::lines($finding, count($ordered), 'php artisan commandments', $absolvable, $autoFixable, $repentInputs) as $line) {
            $this->output->writeln($line);
        }

        return self::FAILURE;
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
        array $gitFiles,
        ?string $pathFilter = null,
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

        if ($pathFilter !== null) {
            return $manager->judgePath($scroll, $pathFilter);
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
        $relativePath = str_replace(Environment::basePath().'/', T_String::empty(), $filePath);
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

            $prophet = new $prophetClass();

            // Process sins
            foreach ($judgment->sins as $sin) {
                $fingerprint = Fingerprint::of($prophetClass, $relativePath, $sin->symbol, $sin->snippet);
                $tracker->markFindingSeen($fingerprint);

                if ($tracker->isFindingAbsolved($fingerprint)) {
                    continue;
                }

                $fileSins++;
                $resolvedAutoFixable = $sin->autoFixable ?? is_a($prophetClass, SinRepenter::class, true);
                $this->trackSin($prophetClass, $relativePath, $sin->line, $sin->message, $resolvedAutoFixable);
            }

            // Process warnings
            foreach ($judgment->warnings as $warning) {
                $fingerprint = Fingerprint::of($prophetClass, $relativePath, $warning->symbol, $warning->snippet);
                $tracker->markFindingSeen($fingerprint);

                if ($tracker->isFindingAbsolved($fingerprint)) {
                    continue;
                }

                $fileWarnings++;
                $this->manualVerificationFiles[$relativePath][] = [
                    'prophet' => class_basename($prophetClass),
                    'message' => $warning->message,
                    'line' => $warning->line,
                ];
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
    private function trackSin(string $prophetClass, string $relativePath, ?int $line, string $message, bool $autoFixable = false): void
    {
        $this->prophetSinCounts[$prophetClass] = ($this->prophetSinCounts[$prophetClass] ?? 0) + 1;
        // Count actually-auto-fixable findings so the summary can distinguish
        // "all mechanical" from "some" from "none" — a SinRepenter prophet often
        // emits findings it cannot mechanically fix (e.g. ExplicitDataFactory's
        // from(object)), where promising `repent` would no-op.
        $this->prophetAutoFixable[$prophetClass] = ($this->prophetAutoFixable[$prophetClass] ?? 0) + ($autoFixable ? 1 : 0);
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
    private function showResults(?string $prophetFilter = null, bool $gitMode = false, bool $stagedMode = false): int
    {
        if ($this->totalSins === 0 && $this->totalWarnings === 0) {
            $this->output->writeln('Righteous: No sins found.');

            return self::SUCCESS;
        }

        $isDetailedView = $prophetFilter !== null;
        $gitFlag = $gitMode ? ' --git' : T_String::empty();

        if ($this->totalSins > 0) {
            $this->output->writeln("SINS: {$this->totalSins} in {$this->totalFiles} files");
            $this->output->newLine();
            $this->output->writeln('DO NOT COMMIT: Fix all sins before committing.');
            $this->output->writeln('You own EVERY finding on a file you touched — pre-existing ones included. "I didn\'t cause this" is never a reason to leave a sin.');
            $this->output->newLine();

            arsort($this->prophetSinCounts);

            foreach ($this->prophetSinCounts as $prophetClass => $count) {
                $shortName = class_basename($prophetClass);
                $fixable = $this->prophetAutoFixable[$prophetClass] ?? 0;
                $autoFixable = match (true) {
                    $fixable <= 0 => T_String::empty(),
                    $fixable >= $count => ' [AUTO-FIXABLE]',
                    default => " [{$fixable}/{$count} AUTO-FIXABLE]",
                };

                $this->output->writeln("- {$shortName} ({$count}){$autoFixable}");

                if ($isDetailedView) {
                    foreach ($this->prophetFileDetails[$prophetClass] ?? [] as $file => $sins) {
                        foreach ($sins as $sin) {
                            $line = $sin['line'] ? ":{$sin['line']}" : T_String::empty();
                            $this->output->writeln("  {$file}{$line}");
                            $this->output->writeln("    {$sin['message']}");
                        }
                    }
                }
            }

            if (!$isDetailedView) {
                $this->output->newLine();
                $this->output->writeln('GUIDED FIX (recommended): walk findings one at a time, full rule shown');
                $this->output->writeln('inline, nothing to scroll past or skip:');
                $this->output->writeln("  php artisan commandments:judge --next{$gitFlag}");
                $this->output->newLine();
                $this->output->writeln('Or fix each sin type manually:');
                $this->output->writeln("  1. Read the rule:    php artisan commandments:scripture --prophet=NAME");
                $this->output->writeln("  2. See the files:    php artisan commandments:judge --prophet=NAME{$gitFlag}");
                $this->output->writeln('  3. Fix all violations following the detailed description exactly');
                $this->output->writeln(T_String::empty());
                $this->output->writeln('Target a subtree:     php artisan commandments:judge --path=<dir>   (ignores all excludes)');
            }

            // Only nudge toward `repent` when at least one finding is genuinely
            // auto-fixable — not merely because a prophet implements SinRepenter
            // (it may have emitted only hand-fix findings, where repent no-ops).
            $hasAutoFixable = array_sum($this->prophetAutoFixable) > 0;

            if ($hasAutoFixable) {
                $this->output->newLine();
                $this->output->writeln('[AUTO-FIXABLE] sins are mechanical — DO NOT fix them by hand. Run:');
                $this->output->writeln("  php artisan commandments:repent{$gitFlag}");
                $this->output->writeln('  (repent rewrites them reliably via AST; hand-fixing wastes effort and risks mistakes.)');
            }
        }

        if ($this->totalWarnings > 0 && ! empty($this->manualVerificationFiles)) {
            $this->output->newLine();
            $this->output->writeln("WARNINGS: {$this->totalWarnings} requiring manual review");
            $this->output->newLine();

            if ($isDetailedView) {
                foreach ($this->manualVerificationFiles as $file => $issues) {
                    foreach ($issues as $issue) {
                        $line = $issue['line'] ? ":{$issue['line']}" : T_String::empty();
                        $this->output->writeln("  {$file}{$line}");
                        $this->output->writeln("    {$issue['message']}");
                    }
                }
            } else {
                $warningProphets = [];
                foreach ($this->manualVerificationFiles as $file => $issues) {
                    foreach ($issues as $issue) {
                        $filterName = str_replace('Prophet', T_String::empty(), $issue['prophet']);
                        $warningProphets[$filterName] = ($warningProphets[$filterName] ?? 0) + 1;
                    }
                }

                foreach ($warningProphets as $filterName => $count) {
                    $this->output->writeln("- {$filterName}Prophet ({$count})");
                }

                $this->output->newLine();
                $this->output->writeln('Each warning carries an APPLY-WHEN / LEAVE-WHEN rubric (use judgment) —');
                $this->output->writeln('but it is NOT ignorable: a staged commit is BLOCKED until every warning');
                $this->output->writeln('is resolved (the pre-commit gate runs `judge --staged`). Walk them one');
                $this->output->writeln('at a time (rubric + full rule shown inline):');
                $this->output->writeln("  php artisan commandments:judge --next{$gitFlag}");
                $this->output->newLine();
                $this->output->writeln('For each: fix it, OR — if the rubric says it does not apply here —');
                $this->output->writeln('absolve it WITH A REASON:');
                $this->output->writeln('  php artisan commandments:absolve --fingerprint=<hash> --reason="why it does not apply"');
            }
        }

        // Pre-commit gate (`judge --staged`): block until every staged finding
        // is resolved — sins fixed, warnings fixed OR absolved with a reason
        // (absolved findings are already filtered from the counts). Other modes
        // stay sins-only.
        if ($stagedMode && $this->totalSins === 0 && $this->totalWarnings > 0) {
            $this->output->writeln(T_String::empty());
            $this->output->writeln("DO NOT COMMIT: {$this->totalWarnings} warning(s) on staged files. Fix each, or absolve it with a reason:");
            $this->output->writeln('  php artisan commandments:absolve --fingerprint=<hash> --reason="why it does not apply here"');
            $this->output->writeln('  many at once? commandments:absolve --warnings --scope=staged --reason="…"  (add --prophet=NAME to scope; --until-push to keep it past the commit until you push)');
        }

        $blocks = $this->totalSins > 0 || ($stagedMode && $this->totalWarnings > 0);

        return $blocks ? self::FAILURE : self::SUCCESS;
    }
}
