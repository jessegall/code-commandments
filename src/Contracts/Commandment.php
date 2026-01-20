<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Contracts;

use JesseGall\CodeCommandments\Results\Judgment;

/**
 * The sacred law each prophet implements.
 * Each commandment defines a rule that code must follow.
 */
interface Commandment
{
    /**
     * Get the short description of this commandment.
     */
    public function description(): string;

    /**
     * Get the full scripture (detailed explanation) of this commandment.
     */
    public function detailedDescription(): string;

    /**
     * Judge a file for transgressions against this commandment.
     */
    public function judge(string $filePath, string $content): Judgment;

    /**
     * Whether this commandment requires manual confession (review).
     * Some sins cannot be automatically detected and require human judgment.
     */
    public function requiresConfession(): bool;

    /**
     * Get the file extensions this commandment applies to.
     *
     * @return array<string>
     */
    public function applicableExtensions(): array;
}
