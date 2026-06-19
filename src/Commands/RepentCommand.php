<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Contracts\Commandment;
use JesseGall\CodeCommandments\Contracts\ParameterizedRepenter;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Results\Finding;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\GitFileDetector;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\RootCauseResolver;
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

    protected $description = 'Auto-fix findings that can be automatically resolved — sins and [AUTO-FIXABLE] warnings (no severity bump needed)';

    /** @var array<string, array<string>> */
    private array $fixedFiles = [];

    /** @var array<string, array<string>> */
    private array $failedFiles = [];

    /** @var array<string, array<string>> prophet => files skipped to avoid laundering a root cause */
    private array $skippedFiles = [];

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
        $totalSkipped = 0;

        foreach ($scrolls as $scroll) {
            if (!$registry->hasScroll($scroll)) {
                continue;
            }

            $prophets = $registry->getProphets($scroll);

            // Cross-file auto-fixes need the full-scroll index; repent runs
            // prophets directly, so inject it the way judgeScroll does.
            $manager->prepareCodebaseIndex($scroll, $prophets);

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

                    // repent acts on auto-fixable findings regardless of severity:
                    // [AUTO-FIXABLE] declares the rewrite SAFE (orthogonal to sin vs
                    // warning), so an [AUTO-FIXABLE] warning is repented without a
                    // severity bump. A parameterized fixer may also act on its
                    // advisory warnings given an --input.
                    $hasWork = ! $judgment->isRighteous()
                        || $this->hasAutoFixableWarning($judgment)
                        || ($parameterized && $judgment->warnings !== []);

                    if (! $hasWork) {
                        continue;
                    }

                    $relativePath = str_replace(Environment::basePath() . '/', T_String::empty(), $filePath);

                    // Auto-fix guard: never launder a symptom whose ROOT CAUSE is
                    // still unresolved in the same region. Skip the file and tell
                    // the agent to fix the cause first; `judge --next` will then
                    // re-surface the cause with its hint.
                    $blockingCause = null;

                    if ($this->hasUnresolvedRootCause($prophet, $judgment, $filePath, $manager, $blockingCause)) {
                        $this->skippedFiles[$prophetName][] = $relativePath . ' (fix ' . $blockingCause . ' first)';
                        $totalSkipped++;

                        continue;
                    }

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

        return $this->showResults($totalFixed, $totalFailed, $totalSkipped, $dryRun);
    }

    /**
     * Whether auto-fixing $prophet on this file would launder a still-unresolved
     * root cause: any auto-fixable finding sits within the defer window of a
     * finding from one of $prophet's declared root-cause prophets. The cause
     * prophets are run on the file directly (with the scroll index) via the same
     * resolver the `judge --next` hint uses, so the guard holds even though
     * `repent` never runs the cause prophets itself.
     */
    private function hasUnresolvedRootCause(
        Commandment $prophet,
        Judgment $judgment,
        string $filePath,
        ScrollManager $manager,
        ?string &$blockingCause,
    ): bool {
        if ($prophet->rootCauses() === []) {
            return false;
        }

        $lines = $this->autoFixableLines($prophet, $judgment);

        if ($lines === []) {
            return false;
        }

        // Fresh resolver per file: its cause-judgment memo must not survive a
        // prior prophet's rewrite of this same file (content changes mid-run).
        $resolver = new RootCauseResolver(
            fn (string $path): ?CodebaseIndex => $manager->codebaseIndexForFile($path),
        );

        foreach ($lines as $line) {
            $probe = new Finding(
                prophetClass: get_class($prophet),
                prophetShort: class_basename($prophet),
                filePath: $filePath,
                relativePath: $filePath,
                kind: 'sin',
                line: $line,
                message: T_String::empty(),
                snippet: null,
                suggestion: null,
                symbol: null,
                advisory: null,
                tier: $prophet->tier(),
                supersedes: [],
                fingerprint: T_String::empty(),
                autoFixable: true,
                rootCauses: $prophet->rootCauses(),
            );

            // active = [] → treat every cause as filtered-out, so all are checked.
            $annotated = $resolver->annotate($probe, []);

            if ($annotated->rootCauseHint !== null) {
                $blockingCause = $annotated->rootCauseHint->causeShort;

                return true;
            }
        }

        return false;
    }

    /**
     * Line numbers of the findings $prophet would actually auto-fix on this file.
     *
     * @return list<int>
     */
    private function autoFixableLines(
        Commandment $prophet,
        Judgment $judgment,
    ): array {
        $repairable = $prophet instanceof SinRepenter;
        $lines = [];

        foreach ($judgment->sins as $sin) {
            if (($sin->autoFixable ?? $repairable) && $sin->line !== null) {
                $lines[] = $sin->line;
            }
        }

        foreach ($judgment->warnings as $warning) {
            if (($warning->autoFixable ?? $repairable) && $warning->line !== null) {
                $lines[] = $warning->line;
            }
        }

        return $lines;
    }

    /**
     * Parse repeatable `--input key=value` tokens into a map.
     *
     * @param  array<int, string>  $tokens
     * @return array<string, string>
     */
    private function hasAutoFixableWarning(\JesseGall\CodeCommandments\Results\Judgment $judgment): bool
    {
        foreach ($judgment->warnings as $warning) {
            if ($warning->autoFixable) {
                return true;
            }
        }

        return false;
    }

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

    private function showResults(int $totalFixed, int $totalFailed, int $totalSkipped, bool $dryRun): int
    {
        if ($totalFixed === 0 && $totalFailed === 0 && $totalSkipped === 0) {
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

        if ($totalSkipped > 0) {
            $this->output->newLine();
            $this->output->writeln("SKIPPED (root cause unresolved — fixing now would HIDE a bug): {$totalSkipped}");
            $this->output->newLine();

            foreach ($this->skippedFiles as $prophet => $files) {
                $this->output->writeln("{$prophet}:");
                foreach ($files as $file) {
                    $this->output->writeln("  {$file}");
                }
            }

            $this->output->newLine();
            $this->output->writeln('These auto-fixes were withheld so an invariant violation is not laundered into');
            $this->output->writeln('a default/Option. Fix the named root cause, then re-run repent — or walk it with');
            $this->output->writeln('`judge --next` (it surfaces the cause with a fix-this-first hint).');
        }

        if ($dryRun) {
            $this->output->newLine();
            $this->output->writeln('Run without --dry-run to apply fixes.');
        }

        return self::SUCCESS;
    }
}
