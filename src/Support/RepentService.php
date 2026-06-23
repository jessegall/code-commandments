<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\CodeCommandments\Contracts\Commandment;
use JesseGall\CodeCommandments\Contracts\ParameterizedRepenter;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Results\Finding;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\PhpTypes\T_String;

/**
 * The shared logic behind `repent` — auto-fix [AUTO-FIXABLE] findings via each
 * prophet's SinRepenter, with the root-cause guard (never launder a symptom whose
 * cause is unresolved), parameterized --input handling, and the FIXED/FAILED/
 * SKIPPED summary. One implementation both command variants call; unifying also
 * lifts the root-cause guard + SKIPPED reporting onto the standalone command,
 * which previously lacked them.
 */
final class RepentService
{
    public const SUCCESS = 0;

    /** @var array<string, list<string>> */
    private array $fixedFiles = [];

    /** @var array<string, list<string>> */
    private array $failedFiles = [];

    /** @var array<string, list<string>> */
    private array $skippedFiles = [];

    /** @var callable(string): void */
    private $emit;

    public function __construct(
        private readonly ScrollManager $manager,
        private readonly ProphetRegistry $registry,
        callable $emit,
    ) {
        $this->emit = $emit;
    }

    /**
     * @param  array<string, mixed>  $opts  scroll, prophet, file, files (list), git, dry_run, input (list of key=value)
     */
    public function run(array $opts): int
    {
        $scrollFilter = $opts['scroll'] ?? null;
        $prophetFilter = $opts['prophet'] ?? null;
        $fileFilter = $opts['file'] ?? null;
        $filesFilter = $opts['files'] ?? [];
        $gitMode = (bool) ($opts['git'] ?? false);
        $dryRun = (bool) ($opts['dry_run'] ?? false);
        $repentInput = $this->parseInputs((array) ($opts['input'] ?? []));

        $gitFiles = [];
        if ($gitMode) {
            $gitFiles = GitFileDetector::for(Environment::basePath())->getChangedFiles();

            if (empty($gitFiles)) {
                ($this->emit)('No changed files in git. Nothing to repent.');

                return self::SUCCESS;
            }
        }

        $scrolls = $scrollFilter ? [$scrollFilter] : $this->registry->getScrolls();

        $totalFixed = 0;
        $totalFailed = 0;
        $totalSkipped = 0;

        foreach ($scrolls as $scroll) {
            if (! $this->registry->hasScroll($scroll)) {
                continue;
            }

            $prophets = $this->registry->getProphets($scroll);

            // Cross-file auto-fixes need the full-scroll index; repent runs prophets
            // directly, so inject it the way judgeScroll does.
            $this->manager->prepareCodebaseIndex($scroll, $prophets);

            foreach ($prophets as $prophet) {
                if (! $prophet instanceof SinRepenter) {
                    continue;
                }

                if ($prophetFilter && ! str_contains(strtolower(class_basename($prophet)), strtolower($prophetFilter))) {
                    continue;
                }

                $prophetName = class_basename($prophet);
                $files = $this->filesFor($scroll, $fileFilter, $filesFilter, $gitMode, $gitFiles);

                foreach ($files as $file) {
                    $filePath = $file->getRealPath();

                    if ($filePath === false || ! $prophet->canRepent($filePath)) {
                        continue;
                    }

                    $content = file_get_contents($filePath);
                    if ($content === false) {
                        continue;
                    }

                    $judgment = $prophet->judge($filePath, $content);
                    $parameterized = $prophet instanceof ParameterizedRepenter;

                    $hasWork = ! $judgment->isRighteous()
                        || $this->hasAutoFixableWarning($judgment)
                        || ($parameterized && $judgment->warnings !== []);

                    if (! $hasWork) {
                        continue;
                    }

                    $relativePath = str_replace(Environment::basePath() . '/', T_String::empty(), $filePath);

                    $blockingCause = null;

                    if ($this->hasUnresolvedRootCause($prophet, $judgment, $filePath, $blockingCause)) {
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
                        if (! $dryRun) {
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
                    } elseif (! $result->absolved) {
                        $this->failedFiles[$prophetName][] = $relativePath . ($result->failureReason ? " ({$result->failureReason})" : T_String::empty());
                        $totalFailed++;
                    }
                }
            }
        }

        return $this->showResults($totalFixed, $totalFailed, $totalSkipped, $dryRun);
    }

    /**
     * @param  array<string>  $filesFilter
     * @param  array<string>  $gitFiles
     * @return iterable<\SplFileInfo>
     */
    private function filesFor(string $scroll, ?string $fileFilter, array $filesFilter, bool $gitMode, array $gitFiles): iterable
    {
        if ($fileFilter) {
            return [new \SplFileInfo($fileFilter)];
        }

        if (! empty($filesFilter)) {
            return array_map(static fn ($f) => new \SplFileInfo($f), $filesFilter);
        }

        if ($gitMode && ! empty($gitFiles)) {
            return array_map(static fn ($f) => new \SplFileInfo($f), $gitFiles);
        }

        return $this->manager->getFilesForScroll($scroll);
    }

    /**
     * @param  string|null  $blockingCause
     */
    private function hasUnresolvedRootCause(Commandment $prophet, Judgment $judgment, string $filePath, ?string &$blockingCause): bool
    {
        if ($prophet->rootCauses() === []) {
            return false;
        }

        $lines = $this->autoFixableLines($prophet, $judgment);

        if ($lines === []) {
            return false;
        }

        $resolver = new RootCauseResolver(
            fn (string $path): ?CodebaseIndex => $this->manager->codebaseIndexForFile($path),
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

            $annotated = $resolver->annotate($probe, []);

            if ($annotated->rootCauseHint !== null) {
                $blockingCause = $annotated->rootCauseHint->causeShort;

                return true;
            }
        }

        return false;
    }

    /**
     * @return list<int>
     */
    private function autoFixableLines(Commandment $prophet, Judgment $judgment): array
    {
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

    private function hasAutoFixableWarning(Judgment $judgment): bool
    {
        foreach ($judgment->warnings as $warning) {
            if ($warning->autoFixable) {
                return true;
            }
        }

        return false;
    }

    /**
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
            if ($spec->required && T_String::isBlank($provided[$spec->name] ?? T_String::empty())) {
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
        ($this->emit)("{$prophetName} can fix {$relativePath}, but needs input:");

        foreach ($missing as $spec) {
            $example = $spec->example !== '' ? $spec->example : '<value>';
            ($this->emit)(sprintf('  --input %s=%s   (required) %s', $spec->name, $example, $spec->description));
        }

        ($this->emit)(T_String::empty());
    }

    private function showResults(int $totalFixed, int $totalFailed, int $totalSkipped, bool $dryRun): int
    {
        if ($totalFixed === 0 && $totalFailed === 0 && $totalSkipped === 0) {
            ($this->emit)('No sins to fix. Code is righteous.');

            return self::SUCCESS;
        }

        $action = $dryRun ? 'WOULD FIX' : 'FIXED';

        if ($totalFixed > 0) {
            ($this->emit)("{$action}: {$totalFixed} sins");
            ($this->emit)(T_String::empty());

            foreach ($this->fixedFiles as $prophet => $files) {
                ($this->emit)("{$prophet}:");
                foreach ($files as $file) {
                    ($this->emit)("  {$file}");
                }
            }
        }

        if ($totalFailed > 0) {
            ($this->emit)(T_String::empty());
            ($this->emit)("FAILED (manual fix required): {$totalFailed}");
            ($this->emit)(T_String::empty());

            foreach ($this->failedFiles as $prophet => $files) {
                ($this->emit)("{$prophet}:");
                foreach ($files as $file) {
                    ($this->emit)("  {$file}");
                }
            }
        }

        if ($totalSkipped > 0) {
            ($this->emit)(T_String::empty());
            ($this->emit)("SKIPPED (root cause unresolved — fixing now would HIDE a bug): {$totalSkipped}");
            ($this->emit)(T_String::empty());

            foreach ($this->skippedFiles as $prophet => $files) {
                ($this->emit)("{$prophet}:");
                foreach ($files as $file) {
                    ($this->emit)("  {$file}");
                }
            }

            ($this->emit)(T_String::empty());
            ($this->emit)('These auto-fixes were withheld so an invariant violation is not laundered into');
            ($this->emit)('a default/Option. Fix the named root cause, then re-run repent — or walk it with');
            ($this->emit)('`judge --next` (it surfaces the cause with a fix-this-first hint).');
        }

        if ($dryRun) {
            ($this->emit)(T_String::empty());
            ($this->emit)('Run without --dry-run to apply fixes.');
        }

        return self::SUCCESS;
    }
}
