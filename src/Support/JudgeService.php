<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\CodeCommandments\Contracts\ConfessionTracker;
use JesseGall\CodeCommandments\Contracts\ParameterizedRepenter;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\Output\NextFindingPresenter;
use JesseGall\PhpTypes\T_String;

/**
 * The shared logic behind `judge` — scope routing, per-file judgment processing,
 * the --next guided mode, and the summary. One implementation both command
 * variants call; they only differ in the runner shown in guidance and the output
 * sink. (Unifying also lifts the standalone's prophet-error reporting onto the
 * artisan command, which previously lacked it.)
 */
final class JudgeService
{
    public const SUCCESS = 0;
    public const FAILURE = 1;

    private int $totalSins = 0;
    private int $totalWarnings = 0;
    private int $totalFiles = 0;

    /** @var array<string, array<array{prophet: string, message: string, line: int|null}>> */
    private array $manualVerificationFiles = [];

    /** @var array<string, int> */
    private array $prophetSinCounts = [];

    /** @var array<string, array<string, array{line: int|null, message: string}>> */
    private array $prophetFileDetails = [];

    /** @var array<string, int> */
    private array $prophetAutoFixable = [];

    /** @var callable(string): void */
    private $emit;

    /** @var callable(string): void */
    private $error;

    public function __construct(
        private readonly ScrollManager $manager,
        private readonly ProphetRegistry $registry,
        private readonly ConfessionTracker $tracker,
        private readonly string $binary,
        private readonly string $sep,
        callable $emit,
        callable $error,
    ) {
        $this->emit = $emit;
        $this->error = $error;
    }

    /**
     * @param  array<string, mixed>  $opts  scroll, prophet, file, files (list), path, git, staged, absolve, no_cache, next
     */
    public function run(array $opts): int
    {
        if ((bool) ($opts['no_cache'] ?? false)) {
            $this->manager->setUseCache(false);
        }

        $scrollFilter = $opts['scroll'] ?? null;
        $prophetFilter = $opts['prophet'] ?? null;
        $this->manager->setProphetFilter($prophetFilter);

        $fileFilter = $opts['file'] ?? null;
        $filesFilter = $opts['files'] ?? [];
        $gitMode = (bool) ($opts['git'] ?? false);
        $stagedMode = (bool) ($opts['staged'] ?? false);
        $pathFilter = $opts['path'] ?? null;
        $shouldAbsolve = (bool) ($opts['absolve'] ?? false);

        $exclusive = array_filter([
            $fileFilter !== null,
            ! empty($filesFilter),
            $gitMode,
            $stagedMode,
            $pathFilter !== null,
        ]);

        if (count($exclusive) > 1) {
            ($this->error)('--file, --files, --git, --staged, and --path are mutually exclusive.');

            return self::FAILURE;
        }

        if ($pathFilter !== null) {
            $resolvedPath = realpath($pathFilter);

            if ($resolvedPath === false || ! is_dir($resolvedPath)) {
                ($this->error)("--path does not point to an existing directory: {$pathFilter}");

                return self::FAILURE;
            }

            $pathFilter = $resolvedPath;
        }

        if ($stagedMode) {
            $gitMode = true;
        }

        $gitFiles = [];
        if ($gitMode) {
            $detector = GitFileDetector::for(Environment::basePath());
            $gitFiles = $stagedMode ? $detector->getStagedFiles() : $detector->getChangedFiles();

            if (empty($gitFiles)) {
                return self::SUCCESS;
            }
        }

        $scrolls = $scrollFilter ? [$scrollFilter] : $this->registry->getScrolls();

        $fullScan = $fileFilter === null
            && empty($filesFilter)
            && ! $gitMode
            && $pathFilter === null
            && $prophetFilter === null;

        if ((bool) ($opts['next'] ?? false)) {
            return $this->runNext($scrolls, $fileFilter, $filesFilter, $gitMode, $gitFiles, $pathFilter, $prophetFilter, $fullScan);
        }

        foreach ($scrolls as $scroll) {
            if (! $this->registry->hasScroll($scroll)) {
                continue;
            }

            foreach ($this->getResults($scroll, $fileFilter, $filesFilter, $gitMode, $gitFiles, $pathFilter) as $filePath => $judgments) {
                $this->processFileJudgments($filePath, $judgments, $prophetFilter, $shouldAbsolve);
            }
        }

        if ($fullScan) {
            $this->tracker->gcUnseenFindings();
        }

        $failures = $this->manager->getFailures();
        $this->showFailures($failures);

        return $this->showResults($prophetFilter, $gitMode, ! empty($failures), $stagedMode);
    }

    /**
     * @param  array<string>  $scrolls
     * @param  array<string>  $filesFilter
     * @param  array<string>  $gitFiles
     */
    private function runNext(array $scrolls, ?string $fileFilter, array $filesFilter, bool $gitMode, array $gitFiles, ?string $pathFilter, ?string $prophetFilter, bool $fullScan): int
    {
        $collector = new FindingCollector($this->tracker);
        $findings = [];

        foreach ($scrolls as $scroll) {
            if (! $this->registry->hasScroll($scroll)) {
                continue;
            }

            $results = $this->getResults($scroll, $fileFilter, $filesFilter, $gitMode, $gitFiles, $pathFilter);
            $findings = array_merge($findings, $collector->collect($results, $prophetFilter, markSeen: true));
        }

        if ($fullScan) {
            $this->tracker->gcUnseenFindings();
        }

        $ordered = FindingQueue::order($findings);

        if ($ordered === []) {
            ($this->emit)(NextFindingPresenter::clearLine());

            return self::SUCCESS;
        }

        $activeProphets = [];
        foreach ($scrolls as $scroll) {
            if ($this->registry->hasScroll($scroll)) {
                $activeProphets += $this->manager->activeProphetClasses($scroll);
            }
        }

        $resolver = new RootCauseResolver(
            fn (string $filePath): ?CodebaseIndex => $this->manager->codebaseIndexForFile($filePath),
        );

        $finding = $resolver->annotate($ordered[0], $activeProphets);
        $prophet = new $finding->prophetClass();
        $absolvable = $finding->isWarning() || $prophet->requiresConfession();
        $autoFixable = $finding->autoFixable;
        $repentInputs = ($autoFixable && $prophet instanceof ParameterizedRepenter) ? $prophet->repentInputs() : null;
        $skillSlug = $prophet->skill();

        foreach (NextFindingPresenter::lines($finding, count($ordered), $this->binary, $absolvable, $autoFixable, $repentInputs, $skillSlug) as $line) {
            ($this->emit)($line);
        }

        return self::FAILURE;
    }

    /**
     * @param  array<string>  $filesFilter
     * @param  array<string>  $gitFiles
     * @return iterable<string, mixed>
     */
    private function getResults(string $scroll, ?string $fileFilter, array $filesFilter, bool $gitMode, array $gitFiles, ?string $pathFilter): iterable
    {
        if ($fileFilter) {
            return [$fileFilter => $this->manager->judgeFile($scroll, $fileFilter)];
        }

        if (! empty($filesFilter)) {
            return $this->manager->judgeFiles($scroll, $filesFilter);
        }

        if ($gitMode && ! empty($gitFiles)) {
            return $this->manager->judgeFiles($scroll, $gitFiles);
        }

        if ($pathFilter !== null) {
            return $this->manager->judgePath($scroll, $pathFilter);
        }

        return $this->manager->judgeScroll($scroll);
    }

    /**
     * @param  iterable<string, mixed>  $judgments
     */
    private function processFileJudgments(string $filePath, $judgments, ?string $prophetFilter, bool $shouldAbsolve): void
    {
        $relativePath = str_replace(Environment::basePath() . '/', T_String::empty(), $filePath);
        $fileSins = 0;
        $fileWarnings = 0;

        foreach ($judgments as $prophetClass => $judgment) {
            if ($prophetFilter && ! str_contains(strtolower(class_basename($prophetClass)), strtolower($prophetFilter))) {
                continue;
            }

            if ($this->isAbsolved($filePath, $prophetClass)) {
                continue;
            }

            foreach ($judgment->sins as $sin) {
                $fingerprint = Fingerprint::of($prophetClass, $relativePath, $sin->symbol, $sin->snippet);
                $this->tracker->markFindingSeen($fingerprint);

                if ($this->tracker->isFindingAbsolved($fingerprint)) {
                    continue;
                }

                $fileSins++;
                $resolvedAutoFixable = $sin->autoFixable ?? is_a($prophetClass, SinRepenter::class, true);
                $this->trackSin($prophetClass, $relativePath, $sin->line, $sin->message, $resolvedAutoFixable);
            }

            foreach ($judgment->warnings as $warning) {
                $fingerprint = Fingerprint::of($prophetClass, $relativePath, $warning->symbol, $warning->snippet);
                $this->tracker->markFindingSeen($fingerprint);

                if ($this->tracker->isFindingAbsolved($fingerprint)) {
                    continue;
                }

                $fileWarnings++;
                $this->manualVerificationFiles[$relativePath][] = [
                    'prophet' => class_basename($prophetClass),
                    'message' => $warning->message,
                    'line' => $warning->line,
                ];
            }

            if ($shouldAbsolve && $judgment->hasWarnings()) {
                $content = file_get_contents($filePath);
                if ($content !== false) {
                    $this->tracker->absolve($filePath, $prophetClass, 'Reviewed via commandments:judge --absolve');
                }
            }
        }

        $this->totalSins += $fileSins;
        $this->totalWarnings += $fileWarnings;

        if ($fileSins > 0 || $fileWarnings > 0) {
            $this->totalFiles++;
        }
    }

    private function trackSin(string $prophetClass, string $relativePath, ?int $line, string $message, bool $autoFixable = false): void
    {
        $this->prophetSinCounts[$prophetClass] = ($this->prophetSinCounts[$prophetClass] ?? 0) + 1;
        $this->prophetAutoFixable[$prophetClass] = ($this->prophetAutoFixable[$prophetClass] ?? 0) + ($autoFixable ? 1 : 0);
        $this->prophetFileDetails[$prophetClass][$relativePath][] = ['line' => $line, 'message' => $message];
    }

    private function isAbsolved(string $filePath, string $prophetClass): bool
    {
        if (! $this->tracker->isAbsolved($filePath, $prophetClass)) {
            return false;
        }

        $content = file_get_contents($filePath);

        return $content !== false && ! $this->tracker->hasChangedSinceAbsolution($filePath, $prophetClass, $content);
    }

    /**
     * @param  list<object>  $failures
     */
    private function showFailures(array $failures): void
    {
        if (empty($failures)) {
            return;
        }

        $grouped = [];
        foreach ($failures as $failure) {
            $grouped[$failure->prophetClass][] = $failure;
        }

        ($this->emit)(T_String::empty());
        ($this->emit)('PROPHET ERRORS: ' . count($failures) . ' (these prophets crashed and were skipped)');
        ($this->emit)(T_String::empty());

        foreach ($grouped as $prophetClass => $prophetFailures) {
            ($this->emit)('- ' . class_basename($prophetClass) . ' (' . count($prophetFailures) . ')');

            foreach ($prophetFailures as $failure) {
                $relative = str_replace(Environment::basePath() . '/', T_String::empty(), $failure->filePath);
                ($this->emit)("    {$relative}");
                ($this->emit)('      ' . get_class($failure->error) . ': ' . $failure->error->getMessage());
            }
        }

        ($this->emit)(T_String::empty());
    }

    private function showResults(?string $prophetFilter, bool $gitMode, bool $hadFailures, bool $stagedMode): int
    {
        $cmd = $this->binary . $this->sep;

        if ($this->totalSins === 0 && $this->totalWarnings === 0) {
            if (! $hadFailures) {
                ($this->emit)('Righteous: No sins found.');
            }

            return $hadFailures ? self::FAILURE : self::SUCCESS;
        }

        $isDetailedView = $prophetFilter !== null;
        $gitFlag = $gitMode ? ' --git' : T_String::empty();

        if ($this->totalSins > 0) {
            ($this->emit)("SINS: {$this->totalSins} in {$this->totalFiles} files");
            ($this->emit)(T_String::empty());
            ($this->emit)('DO NOT COMMIT: Fix all sins before committing.');
            ($this->emit)('You own EVERY finding on a file you touched — pre-existing ones included. "I didn\'t cause this" is never a reason to leave a sin.');
            ($this->emit)(T_String::empty());

            arsort($this->prophetSinCounts);

            foreach ($this->prophetSinCounts as $prophetClass => $count) {
                $shortName = class_basename($prophetClass);
                $fixable = $this->prophetAutoFixable[$prophetClass] ?? 0;
                $autoFixable = match (true) {
                    $fixable <= 0 => T_String::empty(),
                    $fixable >= $count => ' [AUTO-FIXABLE]',
                    default => " [{$fixable}/{$count} AUTO-FIXABLE]",
                };

                ($this->emit)("- {$shortName} ({$count}){$autoFixable}");

                if ($isDetailedView) {
                    foreach ($this->prophetFileDetails[$prophetClass] ?? [] as $file => $sins) {
                        foreach ($sins as $sin) {
                            $line = $sin['line'] ? ":{$sin['line']}" : T_String::empty();
                            ($this->emit)("  {$file}{$line}");
                            ($this->emit)("    {$sin['message']}");
                        }
                    }
                }
            }

            if (! $isDetailedView) {
                ($this->emit)(T_String::empty());
                ($this->emit)('GUIDED FIX (recommended): walk findings one at a time, full rule shown');
                ($this->emit)('inline, nothing to scroll past or skip:');
                ($this->emit)("  {$cmd}judge --next{$gitFlag}");
                ($this->emit)(T_String::empty());
                ($this->emit)('Or fix each sin type manually:');
                ($this->emit)("  1. Read the rule:    {$cmd}scripture --prophet=NAME");
                ($this->emit)("  2. See the files:    {$cmd}judge --prophet=NAME{$gitFlag}");
                ($this->emit)('  3. Fix all violations following the detailed description exactly');
                ($this->emit)(T_String::empty());
                ($this->emit)("Target a subtree:     {$cmd}judge --path=<dir>   (ignores all excludes)");
            }

            $hasAutoFixable = array_sum($this->prophetAutoFixable) > 0;

            if ($hasAutoFixable) {
                ($this->emit)(T_String::empty());
                ($this->emit)('[AUTO-FIXABLE] sins are mechanical — DO NOT fix them by hand. Run:');
                ($this->emit)("  {$cmd}repent{$gitFlag}");
                ($this->emit)('  (repent rewrites them reliably via AST; hand-fixing wastes effort and risks mistakes.)');
            }
        }

        if ($this->totalWarnings > 0 && ! empty($this->manualVerificationFiles)) {
            ($this->emit)(T_String::empty());
            ($this->emit)("WARNINGS: {$this->totalWarnings} requiring manual review");
            ($this->emit)(T_String::empty());

            if ($isDetailedView) {
                foreach ($this->manualVerificationFiles as $file => $issues) {
                    foreach ($issues as $issue) {
                        $line = $issue['line'] ? ":{$issue['line']}" : T_String::empty();
                        ($this->emit)("  {$file}{$line}");
                        ($this->emit)("    {$issue['message']}");
                    }
                }
            } else {
                $warningProphets = [];
                foreach ($this->manualVerificationFiles as $issues) {
                    foreach ($issues as $issue) {
                        $filterName = str_replace('Prophet', T_String::empty(), $issue['prophet']);
                        $warningProphets[$filterName] = ($warningProphets[$filterName] ?? 0) + 1;
                    }
                }

                foreach ($warningProphets as $filterName => $count) {
                    ($this->emit)("- {$filterName}Prophet ({$count})");
                }

                ($this->emit)(T_String::empty());
                ($this->emit)('Each warning carries an APPLY-WHEN / LEAVE-WHEN rubric (use judgment) —');
                ($this->emit)('but it is NOT ignorable: a staged commit is BLOCKED until every warning');
                ($this->emit)('is resolved (the pre-commit gate runs `judge --staged`). Walk them one');
                ($this->emit)('at a time (rubric + full rule shown inline):');
                ($this->emit)("  {$cmd}judge --next{$gitFlag}");
                ($this->emit)(T_String::empty());
                ($this->emit)('For each: fix it, OR — if the rubric says it does not apply here —');
                ($this->emit)('absolve it WITH A REASON:');
                ($this->emit)("  {$cmd}absolve --fingerprint=<hash> --reason=\"why it does not apply\"");
            }
        }

        if ($stagedMode && $this->totalSins === 0 && $this->totalWarnings > 0) {
            ($this->emit)(T_String::empty());
            ($this->emit)("DO NOT COMMIT: {$this->totalWarnings} warning(s) on staged files. Fix each, or absolve it with a reason:");
            ($this->emit)("  {$cmd}absolve --fingerprint=<hash> --reason=\"why it does not apply here\"");
            ($this->emit)("  many at once? {$cmd}absolve --warnings --scope=staged --reason=\"…\"  (add --prophet=NAME to scope; --until-push to keep it past the commit until you push)");
        }

        $blocks = $this->totalSins > 0 || ($stagedMode && $this->totalWarnings > 0) || $hadFailures;

        return $blocks ? self::FAILURE : self::SUCCESS;
    }
}
