<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\GitFileDetector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RepentConsoleCommand extends Command
{
    use BootsStandalone;

    /** @var array<string, array<string>> */
    private array $fixedFiles = [];

    /** @var array<string, array<string>> */
    private array $failedFiles = [];

    protected function configure(): void
    {
        $this
            ->setName('repent')
            ->setDescription('Auto-fix sins that can be automatically resolved')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config file')
            ->addOption('scroll', null, InputOption::VALUE_REQUIRED, 'Filter by specific scroll (group)')
            ->addOption('prophet', null, InputOption::VALUE_REQUIRED, 'Use a specific prophet for repentance')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Repent sins in a specific file')
            ->addOption('files', null, InputOption::VALUE_REQUIRED, 'Repent sins in specific files (comma-separated)')
            ->addOption('git', null, InputOption::VALUE_NONE, 'Only repent files that are new or changed in git')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be fixed without making changes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        [$registry, $manager] = $this->bootEnvironment($input->getOption('config'));

        $scrollFilter = $input->getOption('scroll');
        $prophetFilter = $input->getOption('prophet');
        $fileFilter = $input->getOption('file');
        $filesFilter = $input->getOption('files')
            ? array_map('trim', explode(',', $input->getOption('files')))
            : [];
        $gitMode = (bool) $input->getOption('git');
        $dryRun = (bool) $input->getOption('dry-run');

        $gitFiles = [];
        if ($gitMode) {
            $gitFiles = GitFileDetector::for(Environment::basePath())->getChangedFiles();

            if (empty($gitFiles)) {
                $output->writeln('No changed files in git. Nothing to repent.');

                return Command::SUCCESS;
            }
        }

        $scrolls = $scrollFilter
            ? [$scrollFilter]
            : $registry->getScrolls();

        $totalFixed = 0;
        $totalFailed = 0;

        foreach ($scrolls as $scroll) {
            if (!$registry->hasScroll($scroll)) {
                continue;
            }

            $prophets = $registry->getProphets($scroll);

            foreach ($prophets as $prophet) {
                if (!$prophet instanceof SinRepenter) {
                    continue;
                }

                if ($prophetFilter) {
                    $shortName = class_basename($prophet);
                    if (!str_contains(strtolower($shortName), strtolower($prophetFilter))) {
                        continue;
                    }
                }

                $prophetName = class_basename($prophet);

                if ($fileFilter) {
                    $files = [new \SplFileInfo($fileFilter)];
                } elseif (!empty($filesFilter)) {
                    $files = array_map(fn ($f) => new \SplFileInfo($f), $filesFilter);
                } elseif ($gitMode && !empty($gitFiles)) {
                    $files = array_map(fn ($f) => new \SplFileInfo($f), $gitFiles);
                } else {
                    $files = $manager->getFilesForScroll($scroll);
                }

                foreach ($files as $file) {
                    $filePath = $file->getRealPath();

                    if ($filePath === false || !$prophet->canRepent($filePath)) {
                        continue;
                    }

                    $content = file_get_contents($filePath);
                    if ($content === false) {
                        continue;
                    }

                    $judgment = $prophet->judge($filePath, $content);

                    if ($judgment->isRighteous()) {
                        continue;
                    }

                    $result = $prophet->repent($filePath, $content);
                    $relativePath = str_replace(Environment::basePath() . '/', '', $filePath);

                    if ($result->absolved && $result->newContent !== null) {
                        if (!$dryRun) {
                            file_put_contents($filePath, $result->newContent);
                        }
                        $this->fixedFiles[$prophetName][] = $relativePath;
                        $totalFixed++;
                    } elseif (!$result->absolved) {
                        $this->failedFiles[$prophetName][] = $relativePath . ($result->failureReason ? " ({$result->failureReason})" : '');
                        $totalFailed++;
                    }
                }
            }
        }

        return $this->showResults($output, $totalFixed, $totalFailed, $dryRun);
    }

    private function showResults(OutputInterface $output, int $totalFixed, int $totalFailed, bool $dryRun): int
    {
        if ($totalFixed === 0 && $totalFailed === 0) {
            $output->writeln('No sins to fix. Code is righteous.');

            return Command::SUCCESS;
        }

        $action = $dryRun ? 'WOULD FIX' : 'FIXED';

        if ($totalFixed > 0) {
            $output->writeln("{$action}: {$totalFixed} sins");
            $output->writeln('');

            foreach ($this->fixedFiles as $prophet => $files) {
                $output->writeln("{$prophet}:");
                foreach ($files as $file) {
                    $output->writeln("  {$file}");
                }
            }
        }

        if ($totalFailed > 0) {
            $output->writeln('');
            $output->writeln("FAILED (manual fix required): {$totalFailed}");
            $output->writeln('');

            foreach ($this->failedFiles as $prophet => $files) {
                $output->writeln("{$prophet}:");
                foreach ($files as $file) {
                    $output->writeln("  {$file}");
                }
            }
        }

        if ($dryRun) {
            $output->writeln('');
            $output->writeln('Run without --dry-run to apply fixes.');
        }

        return Command::SUCCESS;
    }
}
