<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Contracts;

/**
 * Interface for scanning files in the codebase.
 */
interface FileScanner
{
    /**
     * Scan the given path for files matching the criteria.
     *
     * @param string $path The base path to scan
     * @param array<string> $extensions File extensions to include
     * @param array<string> $excludePaths Paths to exclude from scanning
     * @return iterable<\SplFileInfo> Iterator of matching files
     */
    public function scan(string $path, array $extensions = [], array $excludePaths = []): iterable;
}
