<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\CodeCommandments\Contracts\Commandment;
use JesseGall\CodeCommandments\Contracts\FileScanner;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Scanners\GenericFileScanner;
use Illuminate\Support\Collection;

/**
 * Manages the sacred scrolls (groups of commandments).
 * Handles scanning files and judging them against commandments.
 */
class ScrollManager
{
    public function __construct(
        protected ProphetRegistry $registry,
        protected FileScanner $scanner,
    ) {}

    /**
     * Judge all files in a scroll.
     *
     * @return Collection<string, Collection<string, Judgment>>
     */
    public function judgeScroll(string $scroll): Collection
    {
        $config = $this->registry->getScrollConfig($scroll);
        $path = $config['path'] ?? base_path();
        $extensions = $config['extensions'] ?? [];
        $excludePaths = $config['exclude'] ?? [];

        $prophets = $this->registry->getProphets($scroll);
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

                $judgment = $prophet->judge($filePath, $content);
                $fileResults->put(get_class($prophet), $judgment);
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
        $extensions = $config['extensions'] ?? [];
        $excludePaths = $config['exclude'] ?? [];

        // Common directories to always exclude
        $defaultExcludes = ['vendor', 'node_modules', 'storage', '.git', 'bootstrap/cache'];

        $prophets = $this->registry->getProphets($scroll);
        $results = collect();

        foreach ($filePaths as $filePath) {
            if (!file_exists($filePath)) {
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

                $judgment = $prophet->judge($filePath, $content);
                $fileResults->put(get_class($prophet), $judgment);
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

            $judgment = $prophet->judge($filePath, $content);
            $results->put(get_class($prophet), $judgment);
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

        // Check configured exclude paths
        foreach ($excludePaths as $excludePath) {
            if (str_contains($filePath, $excludePath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get files that need judgment in a scroll.
     *
     * @return iterable<\SplFileInfo>
     */
    public function getFilesForScroll(string $scroll): iterable
    {
        $config = $this->registry->getScrollConfig($scroll);
        $path = $config['path'] ?? base_path();
        $extensions = $config['extensions'] ?? [];
        $excludePaths = $config['exclude'] ?? [];

        return $this->scanner->scan($path, $extensions, $excludePaths);
    }
}
