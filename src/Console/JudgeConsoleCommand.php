<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Contracts\ConfessionTracker;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
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
        $shouldAbsolve = (bool) $input->getOption('absolve');

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

        return $this->showResults($output);
    }

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

        if (!empty($filesFilter)) {
            return $manager->judgeFiles($scroll, $filesFilter);
        }

        if ($gitMode && !empty($gitFiles)) {
            return $manager->judgeFiles($scroll, $gitFiles);
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

    private function showResults(OutputInterface $output): int
    {
        if ($this->totalSins === 0 && $this->totalWarnings === 0) {
            $output->writeln('Righteous: No sins found.');

            return Command::SUCCESS;
        }

        if ($this->totalSins > 0) {
            $output->writeln("SINS: {$this->totalSins} in {$this->totalFiles} files");
            $output->writeln('');
            $output->writeln('DO NOT COMMIT: Fix all sins before committing.');
            $output->writeln('');

            arsort($this->prophetSinCounts);

            foreach ($this->prophetSinCounts as $prophetClass => $count) {
                $shortName = class_basename($prophetClass);
                $filterName = str_replace('Prophet', '', $shortName);
                $prophet = new $prophetClass();
                $autoFixable = $prophet instanceof SinRepenter ? ' [AUTO-FIXABLE]' : '';

                $output->writeln("{$shortName} ({$count}){$autoFixable}");
                $output->writeln("  {$prophet->description()}");
                $output->writeln("  Details: commandments scripture --prophet={$filterName}");
                $output->writeln('');

                foreach ($this->prophetFileDetails[$prophetClass] ?? [] as $file => $sins) {
                    foreach ($sins as $sin) {
                        $line = $sin['line'] ? ":{$sin['line']}" : '';
                        $output->writeln("  {$file}{$line}");
                        $output->writeln("    {$sin['message']}");
                    }
                }

                $output->writeln('');
            }

            $output->writeln('Auto-fix: commandments repent');
        }

        if ($this->totalWarnings > 0 && !empty($this->manualVerificationFiles)) {
            $output->writeln('');
            $output->writeln("WARNINGS: {$this->totalWarnings} requiring manual review");
            $output->writeln('');

            foreach ($this->manualVerificationFiles as $file => $issues) {
                $output->writeln($file);
                foreach ($issues as $issue) {
                    $line = $issue['line'] ? ":{$issue['line']}" : '';
                    $filterName = str_replace('Prophet', '', $issue['prophet']);
                    $output->writeln("  [{$issue['prophet']}]{$line} {$issue['message']}");
                    $output->writeln("    Details: commandments scripture --prophet={$filterName}");
                }
            }
        }

        return $this->totalSins > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
