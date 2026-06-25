<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Contracts;

use JesseGall\CodeCommandments\Results\RepentanceResult;

/**
 * For prophets that can absolve transgressions through auto-fixing.
 * Uses AST-based transformations for reliable code modifications.
 */
interface SinRepenter
{
    /**
     * Attempt to absolve the sins in a file through auto-fixing.
     *
     * @param string $filePath The path to the file
     * @param string $content The current file content
     * @return RepentanceResult The result of the repentance attempt
     */
    public function repent(string $filePath, string $content): RepentanceResult;

    /**
     * Check if this repenter can absolve the given file type.
     */
    public function canRepent(string $filePath): bool;
}
