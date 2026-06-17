<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Contracts\ParameterizedRepenter;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\GitFileDetector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use JesseGall\PhpTypes\T_String;

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
            ->addOption('input', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Input for a parameterized fixer, repeatable: --input key=value')
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
        $repentInput = $this->parseInputs((array) $input->getOption('input'));

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

                    $parameterized = $prophet instanceof ParameterizedRepenter;

                    // A parameterized fixer may act on advisory warnings too (the
                    // closed-set-enum fix is a warning) — so it is not skipped just
                    // because there are no sins. Other repenters keep sins-only.
                    $hasWork = ! $judgment->isRighteous() || ($parameterized && $judgment->warnings !== []);

                    if (! $hasWork) {
                        continue;
                    }

                    $relativePath = str_replace(Environment::basePath() . '/', T_String::empty(), $filePath);

                    if ($parameterized) {
                        $missing = $this->missingInputs($prophet, $repentInput);

                        if ($missing !== []) {
                            $this->reportMissingInputs($output, $prophetName, $relativePath, $missing);
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

        return $this->showResults($output, $totalFixed, $totalFailed, $dryRun);
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
            $pos = strpos($token, '=');

            if ($pos === false) {
                continue;
            }

            $values[trim(substr($token, 0, $pos))] = substr($token, $pos + 1);
        }

        return $values;
    }

    /**
     * The required inputs a parameterized repenter is still missing.
     *
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
    private function reportMissingInputs(OutputInterface $output, string $prophetName, string $relativePath, array $missing): void
    {
        $output->writeln("{$prophetName} can fix {$relativePath}, but needs input:");

        foreach ($missing as $spec) {
            $example = $spec->example !== '' ? $spec->example : '<value>';
            $output->writeln(sprintf('  --input %s=%s   (required) %s', $spec->name, $example, $spec->description));
        }

        $output->writeln(T_String::empty());
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
            $output->writeln(T_String::empty());

            foreach ($this->fixedFiles as $prophet => $files) {
                $output->writeln("{$prophet}:");
                foreach ($files as $file) {
                    $output->writeln("  {$file}");
                }
            }
        }

        if ($totalFailed > 0) {
            $output->writeln(T_String::empty());
            $output->writeln("FAILED (manual fix required): {$totalFailed}");
            $output->writeln(T_String::empty());

            foreach ($this->failedFiles as $prophet => $files) {
                $output->writeln("{$prophet}:");
                foreach ($files as $file) {
                    $output->writeln("  {$file}");
                }
            }
        }

        if ($dryRun) {
            $output->writeln(T_String::empty());
            $output->writeln('Run without --dry-run to apply fixes.');
        }

        return Command::SUCCESS;
    }
}
