<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Contracts\ConfessionTracker;
use JesseGall\CodeCommandments\Contracts\ParameterizedRepenter;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Results\ProphetFailure;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\FindingCollector;
use JesseGall\CodeCommandments\Support\FindingQueue;
use JesseGall\CodeCommandments\Support\Fingerprint;
use JesseGall\CodeCommandments\Support\GitFileDetector;
use JesseGall\CodeCommandments\Support\Output\NextFindingPresenter;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\ScrollManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use JesseGall\PhpTypes\T_String;

class JudgeConsoleCommand extends Command
{
    use BootsStandalone;

    private int $totalSins = 0;

    private int $totalWarnings = 0;

    private int $totalFiles = 0;

    /** @var array<string, array<array{prophet: string, message: string, line: int|null}>> */
    private array $manualVerificationFiles = [];

    /** @var array<string, int> */
    private array $prophetSinCounts = [];

    /** @var array<string, array<string, array<array{line: int|null, message: string}>>> */
    private array $prophetFileDetails = [];

    /** @var array<string, bool> prophetClass => any finding actually auto-fixable */
    private array $prophetAutoFixable = [];

    protected function configure(): void
    {
        $this
            ->setName('judge')
            ->setDescription('Judge the codebase for sins against the commandments')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config file')
            ->addOption('scroll', null, InputOption::VALUE_REQUIRED, 'Filter by specific scroll (group)')
            ->addOption('prophet', null, InputOption::VALUE_REQUIRED, 'Summon a specific prophet by name')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Judge a specific file')
            ->addOption('files', null, InputOption::VALUE_REQUIRED, 'Judge specific files (comma-separated)')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Override the scroll path and target a specific directory (bypasses all excludes — use to scan subtrees regardless of config)')
            ->addOption('git', null, InputOption::VALUE_NONE, 'Only judge files that are new or changed in git')
            ->addOption('staged', null, InputOption::VALUE_NONE, 'Only judge files staged for commit (what the pre-commit gate uses)')
            ->addOption('absolve', null, InputOption::VALUE_NONE, 'Mark files as absolved after confession')
            ->addOption('next', null, InputOption::VALUE_NONE, 'Show exactly one finding at a time (fix or absolve to advance)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        [$registry, $manager, $tracker] = $this->bootEnvironment($input->getOption('config'));

        $scrollFilter = $input->getOption('scroll');
        $prophetFilter = $input->getOption('prophet');
        $fileFilter = $input->getOption('file');
        $filesFilter = $input->getOption('files')
            ? array_map('trim', explode(',', $input->getOption('files')))
            : [];
        $gitMode = (bool) $input->getOption('git');
        $stagedMode = (bool) $input->getOption('staged');
        $pathFilter = $input->getOption('path');
        $shouldAbsolve = (bool) $input->getOption('absolve');

        $exclusiveFlags = array_filter([
            '--file' => $fileFilter !== null,
            '--files' => ! empty($filesFilter),
            '--git' => $gitMode,
            '--staged' => $stagedMode,
            '--path' => $pathFilter !== null,
        ]);

        if (count($exclusiveFlags) > 1) {
            $output->writeln('<error>--file, --files, --git, --staged, and --path are mutually exclusive.</error>');

            return Command::FAILURE;
        }

        if ($pathFilter !== null) {
            $resolvedPath = realpath($pathFilter);

            if ($resolvedPath === false || ! is_dir($resolvedPath)) {
                $output->writeln("<error>--path does not point to an existing directory: {$pathFilter}</error>");

                return Command::FAILURE;
            }

            $pathFilter = $resolvedPath;
        }

        // --staged reuses the git file-list routing, but with only the files
        // staged for commit — this is what the pre-commit gate judges.
        if ($stagedMode) {
            $gitMode = true;
        }

        $gitFiles = [];
        if ($gitMode) {
            $detector = GitFileDetector::for(Environment::basePath());
            $gitFiles = $stagedMode ? $detector->getStagedFiles() : $detector->getChangedFiles();

            if (empty($gitFiles)) {
                return Command::SUCCESS;
            }
        }

        $scrolls = $scrollFilter
            ? [$scrollFilter]
            : $registry->getScrolls();

        $shouldNext = (bool) $input->getOption('next');

        // Garbage-collect stale finding absolutions, but only on a complete
        // scan — a narrowed run does not see every finding, so unseen ones
        // there are not necessarily gone.
        $fullScan = $fileFilter === null
            && empty($filesFilter)
            && ! $gitMode
            && $pathFilter === null
            && $prophetFilter === null;

        if ($shouldNext) {
            return $this->runNext(
                $output,
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
            if (!$registry->hasScroll($scroll)) {
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

        $failures = $manager->getFailures();
        $this->showFailures($output, $failures);

        $exitCode = $this->showResults($output, $prophetFilter, $gitMode, hadFailures: ! empty($failures), stagedMode: $stagedMode);

        return $exitCode;
    }

    /**
     * @param  array<string>  $scrolls
     * @param  array<string>  $filesFilter
     * @param  array<string>  $gitFiles
     */
    private function runNext(
        OutputInterface $output,
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
            $output->writeln(NextFindingPresenter::clearLine());

            return Command::SUCCESS;
        }

        $finding = $ordered[0];
        $prophet = new $finding->prophetClass();
        $absolvable = $finding->isWarning() || $prophet->requiresConfession();
        // Honour the per-finding flag — a SinRepenter prophet may emit findings
        // that are NOT mechanically fixable (e.g. hand-hydration), and labelling
        // those [AUTO-FIXABLE] sends the agent to a no-op `repent`.
        $autoFixable = $finding->autoFixable;
        $repentInputs = ($autoFixable && $prophet instanceof ParameterizedRepenter) ? $prophet->repentInputs() : null;

        foreach (NextFindingPresenter::lines($finding, count($ordered), 'commandments', $absolvable, $autoFixable, $repentInputs) as $line) {
            $output->writeln($line);
        }

        return Command::FAILURE;
    }

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

        if (!empty($filesFilter)) {
            return $manager->judgeFiles($scroll, $filesFilter);
        }

        if ($gitMode && !empty($gitFiles)) {
            return $manager->judgeFiles($scroll, $gitFiles);
        }

        if ($pathFilter !== null) {
            return $manager->judgePath($scroll, $pathFilter);
        }

        return $manager->judgeScroll($scroll);
    }

    private function processFileJudgments(
        string $filePath,
        $judgments,
        ConfessionTracker $tracker,
        ?string $prophetFilter,
        bool $shouldAbsolve
    ): void {
        $relativePath = str_replace(Environment::basePath() . '/', T_String::empty(), $filePath);
        $fileSins = 0;
        $fileWarnings = 0;

        foreach ($judgments as $prophetClass => $judgment) {
            if ($prophetFilter) {
                $shortName = class_basename($prophetClass);
                if (!str_contains(strtolower($shortName), strtolower($prophetFilter))) {
                    continue;
                }
            }

            if ($this->isAbsolved($filePath, $prophetClass, $tracker)) {
                continue;
            }

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

            if ($shouldAbsolve && $judgment->hasWarnings()) {
                $content = file_get_contents($filePath);
                if ($content !== false) {
                    $tracker->absolve($filePath, $prophetClass, 'Reviewed via commandments judge --absolve');
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
        // Count how many of a prophet's findings are ACTUALLY auto-fixable, so
        // the summary can distinguish "all mechanical" from "some mechanical"
        // from "none" — a SinRepenter prophet routinely emits findings it cannot
        // mechanically fix (e.g. ExplicitDataFactory's from(object) cases), and
        // promising a no-op `repent` on those wastes the agent's cycle.
        $this->prophetAutoFixable[$prophetClass] = ($this->prophetAutoFixable[$prophetClass] ?? 0) + ($autoFixable ? 1 : 0);
        $this->prophetFileDetails[$prophetClass][$relativePath][] = [
            'line' => $line,
            'message' => $message,
        ];
    }

    private function isAbsolved(string $filePath, string $prophetClass, ConfessionTracker $tracker): bool
    {
        if (!$tracker->isAbsolved($filePath, $prophetClass)) {
            return false;
        }

        $content = file_get_contents($filePath);

        return $content !== false && !$tracker->hasChangedSinceAbsolution($filePath, $prophetClass, $content);
    }

    /**
     * @param array<ProphetFailure> $failures
     */
    private function showFailures(OutputInterface $output, array $failures): void
    {
        if (empty($failures)) {
            return;
        }

        $grouped = [];

        foreach ($failures as $failure) {
            $grouped[$failure->prophetClass][] = $failure;
        }

        $total = count($failures);
        $output->writeln(T_String::empty());
        $output->writeln("<comment>PROPHET ERRORS: {$total} (these prophets crashed and were skipped)</comment>");
        $output->writeln(T_String::empty());

        foreach ($grouped as $prophetClass => $prophetFailures) {
            $shortName = class_basename($prophetClass);
            $count = count($prophetFailures);
            $output->writeln("- {$shortName} ({$count})");

            foreach ($prophetFailures as $failure) {
                $relative = str_replace(Environment::basePath() . '/', T_String::empty(), $failure->filePath);
                $output->writeln("    {$relative}");
                $output->writeln("      " . get_class($failure->error) . ': ' . $failure->error->getMessage());
            }
        }

        $output->writeln(T_String::empty());
    }

    private function showResults(OutputInterface $output, ?string $prophetFilter = null, bool $gitMode = false, bool $hadFailures = false, bool $stagedMode = false): int
    {
        if ($this->totalSins === 0 && $this->totalWarnings === 0) {
            if (! $hadFailures) {
                $output->writeln('Righteous: No sins found.');
            }

            return $hadFailures ? Command::FAILURE : Command::SUCCESS;
        }

        $isDetailedView = $prophetFilter !== null;
        $gitFlag = $gitMode ? ' --git' : T_String::empty();

        if ($this->totalSins > 0) {
            $output->writeln("SINS: {$this->totalSins} in {$this->totalFiles} files");
            $output->writeln(T_String::empty());
            $output->writeln('DO NOT COMMIT: Fix all sins before committing.');
            $output->writeln('You own EVERY finding on a file you touched — pre-existing ones included. "I didn\'t cause this" is never a reason to leave a sin.');
            $output->writeln(T_String::empty());

            arsort($this->prophetSinCounts);

            foreach ($this->prophetSinCounts as $prophetClass => $count) {
                $shortName = class_basename($prophetClass);
                $fixable = $this->prophetAutoFixable[$prophetClass] ?? 0;
                $autoFixable = match (true) {
                    $fixable <= 0 => T_String::empty(),
                    $fixable >= $count => ' [AUTO-FIXABLE]',
                    default => " [{$fixable}/{$count} AUTO-FIXABLE]",
                };

                $output->writeln("- {$shortName} ({$count}){$autoFixable}");

                if ($isDetailedView) {
                    foreach ($this->prophetFileDetails[$prophetClass] ?? [] as $file => $sins) {
                        foreach ($sins as $sin) {
                            $line = $sin['line'] ? ":{$sin['line']}" : T_String::empty();
                            $output->writeln("  {$file}{$line}");
                            $output->writeln("    {$sin['message']}");
                        }
                    }
                }
            }

            if (!$isDetailedView) {
                $output->writeln(T_String::empty());
                $output->writeln('GUIDED FIX (recommended): walk findings one at a time, full rule shown');
                $output->writeln('inline, nothing to scroll past or skip:');
                $output->writeln("  commandments judge --next{$gitFlag}");
                $output->writeln(T_String::empty());
                $output->writeln('Or fix each sin type manually:');
                $output->writeln("  1. Read the rule:    commandments scripture --prophet=NAME");
                $output->writeln("  2. See the files:    commandments judge --prophet=NAME{$gitFlag}");
                $output->writeln('  3. Fix all violations following the detailed description exactly');
                $output->writeln(T_String::empty());
                $output->writeln('Target a subtree:     commandments judge --path=<dir>   (ignores all excludes)');
            }

            // Only nudge toward `repent` when at least one finding is genuinely
            // auto-fixable — not merely because a prophet implements SinRepenter
            // (it may have emitted only hand-fix findings, where repent no-ops).
            $hasAutoFixable = array_sum($this->prophetAutoFixable) > 0;

            if ($hasAutoFixable) {
                $output->writeln(T_String::empty());
                $output->writeln('[AUTO-FIXABLE] sins are mechanical — DO NOT fix them by hand. Run:');
                $output->writeln("  commandments repent{$gitFlag}");
                $output->writeln('  (repent rewrites them reliably via AST; hand-fixing wastes effort and risks mistakes.)');
            }
        }

        if ($this->totalWarnings > 0 && !empty($this->manualVerificationFiles)) {
            $output->writeln(T_String::empty());
            $output->writeln("WARNINGS: {$this->totalWarnings} requiring manual review");
            $output->writeln(T_String::empty());

            if ($isDetailedView) {
                foreach ($this->manualVerificationFiles as $file => $issues) {
                    foreach ($issues as $issue) {
                        $line = $issue['line'] ? ":{$issue['line']}" : T_String::empty();
                        $output->writeln("  {$file}{$line}");
                        $output->writeln("    {$issue['message']}");
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
                    $output->writeln("- {$filterName}Prophet ({$count})");
                }

                $output->writeln(T_String::empty());
                $output->writeln('Each warning carries an APPLY-WHEN / LEAVE-WHEN rubric (use judgment) —');
                $output->writeln('but it is NOT ignorable: a staged commit is BLOCKED until every warning');
                $output->writeln('is resolved (the pre-commit gate runs `judge --staged`). Walk them one');
                $output->writeln('at a time (rubric + full rule shown inline):');
                $output->writeln("  commandments judge --next{$gitFlag}");
                $output->writeln(T_String::empty());
                $output->writeln('For each: fix it, OR — if the rubric says it does not apply here —');
                $output->writeln('absolve it WITH A REASON:');
                $output->writeln('  commandments absolve --fingerprint=<hash> --reason="why it does not apply"');
            }
        }

        // The pre-commit gate runs `judge --staged`: a commit is blocked until
        // every finding on the staged files is resolved — sins fixed, and each
        // warning fixed OR absolved WITH A REASON (absolved findings are already
        // filtered out of the counts above). Other modes stay sins-only.
        if ($stagedMode && $this->totalSins === 0 && $this->totalWarnings > 0) {
            $output->writeln(T_String::empty());
            $output->writeln("DO NOT COMMIT: {$this->totalWarnings} warning(s) on staged files. Fix each, or absolve it with a reason:");
            $output->writeln('  commandments absolve --fingerprint=<hash> --reason="why it does not apply here"');
        }

        $blocks = $this->totalSins > 0 || ($stagedMode && $this->totalWarnings > 0);

        return $blocks ? Command::FAILURE : Command::SUCCESS;
    }
}
