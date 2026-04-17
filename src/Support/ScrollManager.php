<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\CodeCommandments\Contracts\Commandment;
use JesseGall\CodeCommandments\Contracts\FileScanner;
use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\ProphetFailure;
use JesseGall\CodeCommandments\Scanners\GenericFileScanner;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\Environment;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Manages the sacred scrolls (groups of commandments).
 * Handles scanning files and judging them against commandments.
 */
class ScrollManager
{
    /** @var array<ProphetFailure> */
    protected array $failures = [];

    public function __construct(
        protected ProphetRegistry $registry,
        protected FileScanner $scanner,
    ) {
        ProphetExecutionContext::register();
    }

    /**
     * @return array<ProphetFailure>
     */
    public function getFailures(): array
    {
        return $this->failures;
    }

    protected function runProphet(Commandment $prophet, string $filePath, string $content): ?Judgment
    {
        ProphetExecutionContext::enter(get_class($prophet), $filePath);

        try {
            return $prophet->judge($filePath, $content);
        } catch (Throwable $e) {
            $this->failures[] = new ProphetFailure(get_class($prophet), $filePath, $e);

            return null;
        } finally {
            ProphetExecutionContext::leave();
        }
    }

    /**
     * Judge all files in a scroll.
     *
     * @return Collection<string, Collection<string, Judgment>>
     */
    public function judgeScroll(string $scroll): Collection
    {
        $config = $this->registry->getScrollConfig($scroll);
        $path = $config['path'] ?? Environment::basePath();
        $extensions = $config['extensions'] ?? [];
        $excludePaths = $config['exclude'] ?? [];

        $prophets = $this->registry->getProphets($scroll);
        $this->injectCodebaseIndex($prophets, $this->buildCodebaseIndex($scroll));

        $results = collect();

        foreach ($this->scanner->scan($path, $extensions, $excludePaths) as $file) {
            $filePath = $file->getRealPath();

            if ($filePath === false) {
                continue;
            }

            $content = file_get_contents($filePath);

            if ($content === false) {
                continue;
            }

            $fileResults = collect();

            foreach ($prophets as $prophet) {
                if (!$this->isProphetApplicable($prophet, $filePath)) {
                    continue;
                }

                $judgment = $this->runProphet($prophet, $filePath, $content);

                if ($judgment !== null) {
                    $fileResults->put(get_class($prophet), $judgment);
                }
            }

            if ($fileResults->isNotEmpty()) {
                $results->put($filePath, $fileResults);
            }
        }

        return $results;
    }

    /**
     * Judge specific files in a scroll.
     *
     * @param  array<string>  $filePaths
     * @return Collection<string, Collection<string, Judgment>>
     */
    public function judgeFiles(string $scroll, array $filePaths): Collection
    {
        $config = $this->registry->getScrollConfig($scroll);
        $path = $config['path'] ?? Environment::basePath();
        $extensions = $config['extensions'] ?? [];
        $excludePaths = $config['exclude'] ?? [];

        // Common directories to always exclude
        $defaultExcludes = ['vendor', 'node_modules', 'storage', '.git', 'bootstrap/cache'];

        $prophets = $this->registry->getProphets($scroll);
        // Build the index from the FULL scroll so cross-file tracing can still
        // see callers that live outside the --git file set.
        $this->injectCodebaseIndex($prophets, $this->buildCodebaseIndex($scroll));

        $results = collect();

        // Normalize the scroll path for comparison
        $scrollPath = realpath($path);

        foreach ($filePaths as $filePath) {
            if (!file_exists($filePath)) {
                continue;
            }

            // Only judge files within the scroll's configured path
            if ($scrollPath !== false && !str_starts_with(realpath($filePath), $scrollPath)) {
                continue;
            }

            // Check if file is in an excluded path
            if ($this->isExcluded($filePath, $excludePaths, $defaultExcludes)) {
                continue;
            }

            $extension = pathinfo($filePath, PATHINFO_EXTENSION);
            if (!empty($extensions) && !in_array($extension, $extensions, true)) {
                continue;
            }

            $content = file_get_contents($filePath);

            if ($content === false) {
                continue;
            }

            $fileResults = collect();

            foreach ($prophets as $prophet) {
                if (!$this->isProphetApplicable($prophet, $filePath)) {
                    continue;
                }

                $judgment = $this->runProphet($prophet, $filePath, $content);

                if ($judgment !== null) {
                    $fileResults->put(get_class($prophet), $judgment);
                }
            }

            if ($fileResults->isNotEmpty()) {
                $results->put($filePath, $fileResults);
            }
        }

        return $results;
    }

    /**
     * Judge every file under a specific directory, overriding the scroll's
     * configured path AND bypassing every exclude (default + configured).
     * The caller is explicitly targeting a subtree and we trust them.
     *
     * @return Collection<string, Collection<string, Judgment>>
     */
    public function judgePath(string $scroll, string $path): Collection
    {
        $config = $this->registry->getScrollConfig($scroll);
        $extensions = $config['extensions'] ?? [];

        $prophets = $this->registry->getProphets($scroll);
        // Build the index from the FULL scroll (not the narrowed path) so
        // upstream callers outside the targeted subtree still resolve.
        $this->injectCodebaseIndex($prophets, $this->buildCodebaseIndex($scroll));

        $results = collect();

        foreach ($this->scanner->scan($path, $extensions, [], honorDefaultExcludes: false) as $file) {
            $filePath = $file->getRealPath();

            if ($filePath === false) {
                continue;
            }

            $content = file_get_contents($filePath);

            if ($content === false) {
                continue;
            }

            $fileResults = collect();

            foreach ($prophets as $prophet) {
                if (!$this->isProphetApplicable($prophet, $filePath)) {
                    continue;
                }

                $judgment = $this->runProphet($prophet, $filePath, $content);

                if ($judgment !== null) {
                    $fileResults->put(get_class($prophet), $judgment);
                }
            }

            if ($fileResults->isNotEmpty()) {
                $results->put($filePath, $fileResults);
            }
        }

        return $results;
    }

    /**
     * Judge a specific file against all applicable prophets in a scroll.
     *
     * Single-file mode skips the cross-file codebase index build — tracing
     * is inherently useless here and the cost would dominate a single-file
     * run.
     *
     * @return Collection<string, Judgment>
     */
    public function judgeFile(string $scroll, string $filePath): Collection
    {
        $content = file_get_contents($filePath);

        if ($content === false) {
            return collect();
        }

        $prophets = $this->registry->getProphets($scroll);
        $results = collect();

        foreach ($prophets as $prophet) {
            if (!$this->isProphetApplicable($prophet, $filePath)) {
                continue;
            }

            $judgment = $this->runProphet($prophet, $filePath, $content);

            if ($judgment !== null) {
                $results->put(get_class($prophet), $judgment);
            }
        }

        return $results;
    }

    /**
     * Judge all scrolls.
     *
     * @return Collection<string, Collection<string, Collection<string, Judgment>>>
     */
    public function judgeAllScrolls(): Collection
    {
        return collect($this->registry->getScrolls())
            ->mapWithKeys(fn (string $scroll) => [$scroll => $this->judgeScroll($scroll)]);
    }

    /**
     * Get summary statistics for a scroll judgment.
     *
     * @param Collection<string, Collection<string, Judgment>> $results
     * @return array{files: int, sins: int, warnings: int, righteous: int, fallen: int}
     */
    public function getSummary(Collection $results): array
    {
        $summary = [
            'files' => $results->count(),
            'sins' => 0,
            'warnings' => 0,
            'righteous' => 0,
            'fallen' => 0,
        ];

        foreach ($results as $fileResults) {
            $fileSins = 0;
            $fileWarnings = 0;

            foreach ($fileResults as $judgment) {
                $fileSins += $judgment->sinCount();
                $fileWarnings += $judgment->warningCount();
            }

            $summary['sins'] += $fileSins;
            $summary['warnings'] += $fileWarnings;

            if ($fileSins > 0) {
                $summary['fallen']++;
            } else {
                $summary['righteous']++;
            }
        }

        return $summary;
    }

    /**
     * Check if a prophet is applicable to a file.
     */
    protected function isProphetApplicable(Commandment $prophet, string $filePath): bool
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $applicableExtensions = $prophet->applicableExtensions();

        if (!empty($applicableExtensions) && !in_array($extension, $applicableExtensions, true)) {
            return false;
        }

        // Check prophet-specific exclusions
        foreach ($prophet->getExcludedPaths() as $excludePath) {
            if (str_contains($filePath, $excludePath)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a file path should be excluded.
     *
     * @param  array<string>  $excludePaths
     * @param  array<string>  $defaultExcludes
     */
    protected function isExcluded(string $filePath, array $excludePaths, array $defaultExcludes): bool
    {
        // Check default excludes (directory names)
        foreach ($defaultExcludes as $exclude) {
            if (str_contains($filePath, DIRECTORY_SEPARATOR.$exclude.DIRECTORY_SEPARATOR) ||
                str_contains($filePath, '/'.$exclude.'/')) {
                return true;
            }
        }

        return PathExcludeMatcher::matchesAny($filePath, $excludePaths);
    }

    /**
     * Get files that need judgment in a scroll.
     *
     * @return iterable<\SplFileInfo>
     */
    public function getFilesForScroll(string $scroll): iterable
    {
        $config = $this->registry->getScrollConfig($scroll);
        $path = $config['path'] ?? Environment::basePath();
        $extensions = $config['extensions'] ?? [];
        $excludePaths = $config['exclude'] ?? [];

        return $this->scanner->scan($path, $extensions, $excludePaths);
    }

    /**
     * Build a codebase index for every PHP file in a scroll.
     */
    protected function buildCodebaseIndex(string $scroll): CodebaseIndex
    {
        $config = $this->registry->getScrollConfig($scroll);
        $path = $config['path'] ?? Environment::basePath();
        $extensions = $config['extensions'] ?? [];
        $excludePaths = $config['exclude'] ?? [];

        if (! in_array('php', $extensions, true)) {
            return CodebaseIndex::build([]);
        }

        $files = [];

        foreach ($this->scanner->scan($path, ['php'], $excludePaths) as $file) {
            $real = $file->getRealPath();

            if ($real !== false) {
                $files[] = $real;
            }
        }

        return CodebaseIndex::build($files);
    }

    /**
     * Inject the codebase index into every prophet that implements
     * `NeedsCodebaseIndex`.
     *
     * @param  iterable<Commandment>  $prophets
     */
    protected function injectCodebaseIndex(iterable $prophets, CodebaseIndex $index): void
    {
        foreach ($prophets as $prophet) {
            if ($prophet instanceof NeedsCodebaseIndex) {
                $prophet->setCodebaseIndex($index);
            }
        }
    }
}
