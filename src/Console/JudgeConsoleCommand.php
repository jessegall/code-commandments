<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Contracts\ConfessionTracker;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Results\ProphetFailure;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\GitFileDetector;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\ScrollManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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
            ->addOption('absolve', null, InputOption::VALUE_NONE, 'Mark files as absolved after confession');
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
        $pathFilter = $input->getOption('path');
        $shouldAbsolve = (bool) $input->getOption('absolve');

        $exclusiveFlags = array_filter([
            '--file' => $fileFilter !== null,
            '--files' => ! empty($filesFilter),
            '--git' => $gitMode,
            '--path' => $pathFilter !== null,
        ]);

        if (count($exclusiveFlags) > 1) {
            $output->writeln('<error>--file, --files, --git, and --path are mutually exclusive.</error>');

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

        $gitFiles = [];
        if ($gitMode) {
            $gitFiles = GitFileDetector::for(Environment::basePath())->getChangedFiles();

            if (empty($gitFiles)) {
                return Command::SUCCESS;
            }
        }

        $scrolls = $scrollFilter
            ? [$scrollFilter]
            : $registry->getScrolls();

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

        $failures = $manager->getFailures();
        $this->showFailures($output, $failures);

        $exitCode = $this->showResults($output, $prophetFilter, $gitMode, hadFailures: ! empty($failures));

        return $exitCode;
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
        $relativePath = str_replace(Environment::basePath() . '/', '', $filePath);
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
                $fileSins++;
                $this->trackSin($prophetClass, $relativePath, $sin->line, $sin->message);
            }

            foreach ($judgment->warnings as $warning) {
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

    private function trackSin(string $prophetClass, string $relativePath, ?int $line, string $message): void
    {
        $this->prophetSinCounts[$prophetClass] = ($this->prophetSinCounts[$prophetClass] ?? 0) + 1;
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
        $output->writeln('');
        $output->writeln("<comment>PROPHET ERRORS: {$total} (these prophets crashed and were skipped)</comment>");
        $output->writeln('');

        foreach ($grouped as $prophetClass => $prophetFailures) {
            $shortName = class_basename($prophetClass);
            $count = count($prophetFailures);
            $output->writeln("- {$shortName} ({$count})");

            foreach ($prophetFailures as $failure) {
                $relative = str_replace(Environment::basePath() . '/', '', $failure->filePath);
                $output->writeln("    {$relative}");
                $output->writeln("      " . get_class($failure->error) . ': ' . $failure->error->getMessage());
            }
        }

        $output->writeln('');
    }

    private function showResults(OutputInterface $output, ?string $prophetFilter = null, bool $gitMode = false, bool $hadFailures = false): int
    {
        if ($this->totalSins === 0 && $this->totalWarnings === 0) {
            if (! $hadFailures) {
                $output->writeln('Righteous: No sins found.');
            }

            return $hadFailures ? Command::FAILURE : Command::SUCCESS;
        }

        $isDetailedView = $prophetFilter !== null;
        $gitFlag = $gitMode ? ' --git' : '';

        if ($this->totalSins > 0) {
            $output->writeln("SINS: {$this->totalSins} in {$this->totalFiles} files");
            $output->writeln('');
            $output->writeln('DO NOT COMMIT: Fix all sins before committing.');
            $output->writeln('');

            arsort($this->prophetSinCounts);

            foreach ($this->prophetSinCounts as $prophetClass => $count) {
                $shortName = class_basename($prophetClass);
                $prophet = new $prophetClass();
                $autoFixable = $prophet instanceof SinRepenter ? ' [AUTO-FIXABLE]' : '';

                $output->writeln("- {$shortName} ({$count}){$autoFixable}");

                if ($isDetailedView) {
                    foreach ($this->prophetFileDetails[$prophetClass] ?? [] as $file => $sins) {
                        foreach ($sins as $sin) {
                            $line = $sin['line'] ? ":{$sin['line']}" : '';
                            $output->writeln("  {$file}{$line}");
                            $output->writeln("    {$sin['message']}");
                        }
                    }
                }
            }

            if (!$isDetailedView) {
                $output->writeln('');
                $output->writeln('FIX EACH SIN TYPE: Process one at a time, in order:');
                $output->writeln("  1. Read the rule:    commandments scripture --prophet=NAME");
                $output->writeln("  2. See the files:    commandments judge --prophet=NAME{$gitFlag}");
                $output->writeln('  3. Fix all violations following the detailed description exactly');
                $output->writeln('  4. Move to the next sin type');
                $output->writeln('');
                $output->writeln('Target a subtree:     commandments judge --path=<dir>   (ignores all excludes)');
            }

            $hasAutoFixable = false;
            foreach ($this->prophetSinCounts as $prophetClass => $count) {
                if (new $prophetClass() instanceof SinRepenter) {
                    $hasAutoFixable = true;
                    break;
                }
            }

            if ($hasAutoFixable) {
                $output->writeln('');
                $output->writeln("[AUTO-FIXABLE] sins can be fixed with: commandments repent{$gitFlag}");
            }
        }

        if ($this->totalWarnings > 0 && !empty($this->manualVerificationFiles)) {
            $output->writeln('');
            $output->writeln("WARNINGS: {$this->totalWarnings} requiring manual review");
            $output->writeln('');

            if ($isDetailedView) {
                foreach ($this->manualVerificationFiles as $file => $issues) {
                    foreach ($issues as $issue) {
                        $line = $issue['line'] ? ":{$issue['line']}" : '';
                        $output->writeln("  {$file}{$line}");
                        $output->writeln("    {$issue['message']}");
                    }
                }
            } else {
                $warningProphets = [];
                foreach ($this->manualVerificationFiles as $file => $issues) {
                    foreach ($issues as $issue) {
                        $filterName = str_replace('Prophet', '', $issue['prophet']);
                        $warningProphets[$filterName] = ($warningProphets[$filterName] ?? 0) + 1;
                    }
                }

                foreach ($warningProphets as $filterName => $count) {
                    $output->writeln("- {$filterName}Prophet ({$count})");
                }

                $output->writeln('');
                $output->writeln('REVIEW EACH WARNING TYPE: Process one at a time:');
                $output->writeln("  1. Read the rule:    commandments scripture --prophet=NAME");
                $output->writeln("  2. See the files:    commandments judge --prophet=NAME{$gitFlag}");
                $output->writeln('  3. Review and fix following the detailed description exactly');
                $output->writeln('');
                $output->writeln('NOTE: Warnings in files you just created or edited MUST be investigated.');
                $output->writeln('Warnings in files you did not touch can be ignored.');
            }
        }

        return $this->totalSins > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
