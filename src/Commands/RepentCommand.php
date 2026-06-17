<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Contracts\ParameterizedRepenter;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\GitFileDetector;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\ScrollManager;
use JesseGall\PhpTypes\T_String;

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
        {--input=* : Input for a parameterized fixer, repeatable: --input key=value}
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
        $repentInput = $this->parseInputs((array) $this->option('input'));

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

                    $parameterized = $prophet instanceof ParameterizedRepenter;

                    // A parameterized fixer may act on advisory warnings too, so
                    // it is not skipped just because there are no sins.
                    $hasWork = ! $judgment->isRighteous() || ($parameterized && $judgment->warnings !== []);

                    if (! $hasWork) {
                        continue;
                    }

                    $relativePath = str_replace(Environment::basePath() . '/', T_String::empty(), $filePath);

                    if ($parameterized) {
                        $missing = $this->missingInputs($prophet, $repentInput);

                        if ($missing !== []) {
                            $this->reportMissingInputs($prophetName, $relativePath, $missing);
                            $this->failedFiles[$prophetName][] = $relativePath . ' (missing required --input)';
                            $totalFailed++;

                            continue;
                        }

                        $prophet->setRepentInput($repentInput);
                    }

                    $result = $prophet->repent($filePath, $content);

                    if ($result->absolved && $result->newContent !== null) {
                        if (!$dryRun) {
                            file_put_contents($filePath, $result->newContent);

                            foreach ($result->createdFiles as $newPath => $newFileContent) {
                                file_put_contents($newPath, $newFileContent);
                            }
                        }
                        $this->fixedFiles[$prophetName][] = $relativePath;

                        foreach (array_keys($result->createdFiles) as $newPath) {
                            $created = str_replace(Environment::basePath() . '/', T_String::empty(), $newPath);
                            $this->fixedFiles[$prophetName][] = "{$created} (created)";
                        }
                        $totalFixed++;
                    } elseif (!$result->absolved) {
                        $this->failedFiles[$prophetName][] = $relativePath . ($result->failureReason ? " ({$result->failureReason})" : T_String::empty());
                        $totalFailed++;
                    }
                }
            }
        }

        return $this->showResults($totalFixed, $totalFailed, $dryRun);
    }

    /**
     * Parse repeatable `--input key=value` tokens into a map.
     *
     * @param  array<int, string>  $tokens
     * @return array<string, string>
     */
    private function parseInputs(array $tokens): array
    {
        $values = [];

        foreach ($tokens as $token) {
            $pos = strpos((string) $token, '=');

            if ($pos === false) {
                continue;
            }

            $values[trim(substr((string) $token, 0, $pos))] = substr((string) $token, $pos + 1);
        }

        return $values;
    }

    /**
     * @param  array<string, string>  $provided
     * @return list<\JesseGall\CodeCommandments\Results\RepentInput>
     */
    private function missingInputs(ParameterizedRepenter $prophet, array $provided): array
    {
        $missing = [];

        foreach ($prophet->repentInputs() as $spec) {
            if ($spec->required && trim($provided[$spec->name] ?? '') === '') {
                $missing[] = $spec;
            }
        }

        return $missing;
    }

    /**
     * @param  list<\JesseGall\CodeCommandments\Results\RepentInput>  $missing
     */
    private function reportMissingInputs(string $prophetName, string $relativePath, array $missing): void
    {
        $this->output->writeln("{$prophetName} can fix {$relativePath}, but needs input:");

        foreach ($missing as $spec) {
            $example = $spec->example !== '' ? $spec->example : '<value>';
            $this->output->writeln(sprintf('  --input %s=%s   (required) %s', $spec->name, $example, $spec->description));
        }

        $this->output->writeln(T_String::empty());
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
