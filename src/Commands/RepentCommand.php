<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\GitFileDetector;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\ScrollManager;

/**
 * Auto-fix sins that can be automatically resolved.
 */
class RepentCommand extends Command
{
    protected $signature = 'commandments:repent
        {--scroll= : Filter by specific scroll (group)}
        {--prophet= : Use a specific prophet for repentance}
        {--file= : Repent sins in a specific file}
        {--files= : Repent sins in specific files (comma-separated)}
        {--git : Only repent files that are new or changed in git}
        {--dry-run : Show what would be fixed without making changes}';

    protected $description = 'Auto-fix sins that can be automatically resolved';

    /** @var array<string, array<string>> */
    private array $fixedFiles = [];

    /** @var array<string, array<string>> */
    private array $failedFiles = [];

    public function handle(
        ProphetRegistry $registry,
        ScrollManager $manager
    ): int {
        $scrollFilter = $this->option('scroll');
        $prophetFilter = $this->option('prophet');
        $fileFilter = $this->option('file');
        $filesFilter = $this->option('files')
            ? array_map('trim', explode(',', $this->option('files')))
            : [];
        $gitMode = (bool) $this->option('git');
        $dryRun = $this->option('dry-run');

        $gitFiles = [];
        if ($gitMode) {
            $gitFiles = GitFileDetector::for(Environment::basePath())->getChangedFiles();

            if (empty($gitFiles)) {
                $this->output->writeln('No changed files in git. Nothing to repent.');

                return self::SUCCESS;
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

        return $this->showResults($totalFixed, $totalFailed, $dryRun);
    }

    private function showResults(int $totalFixed, int $totalFailed, bool $dryRun): int
    {
        if ($totalFixed === 0 && $totalFailed === 0) {
            $this->output->writeln('No sins to fix. Code is righteous.');

            return self::SUCCESS;
        }

        $action = $dryRun ? 'WOULD FIX' : 'FIXED';

        if ($totalFixed > 0) {
            $this->output->writeln("{$action}: {$totalFixed} sins");
            $this->output->newLine();

            foreach ($this->fixedFiles as $prophet => $files) {
                $this->output->writeln("{$prophet}:");
                foreach ($files as $file) {
                    $this->output->writeln("  {$file}");
                }
            }
        }

        if ($totalFailed > 0) {
            $this->output->newLine();
            $this->output->writeln("FAILED (manual fix required): {$totalFailed}");
            $this->output->newLine();

            foreach ($this->failedFiles as $prophet => $files) {
                $this->output->writeln("{$prophet}:");
                foreach ($files as $file) {
                    $this->output->writeln("  {$file}");
                }
            }
        }

        if ($dryRun) {
            $this->output->newLine();
            $this->output->writeln('Run without --dry-run to apply fixes.');
        }

        return self::SUCCESS;
    }
}
