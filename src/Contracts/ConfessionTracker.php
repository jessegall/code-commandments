<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Contracts;

/**
 * For tracking files that have been manually reviewed (absolved through confession).
 * Some sins require human judgment rather than automated detection.
 */
interface ConfessionTracker
{
    /**
     * Mark a file as absolved for a specific commandment.
     *
     * @param string $filePath The file that was reviewed
     * @param string $commandmentClass The commandment class name
     * @param string|null $reason Optional reason for absolution
     */
    public function absolve(string $filePath, string $commandmentClass, ?string $reason = null): void;

    /**
     * Check if a file has been absolved for a specific commandment.
     */
    public function isAbsolved(string $filePath, string $commandmentClass): bool;

    /**
     * Revoke absolution for a file (e.g., when file content changes).
     */
    public function revokeAbsolution(string $filePath, string $commandmentClass): void;

    /**
     * Get all absolutions for a file.
     *
     * @return array<string, array{absolved_at: string, reason: string|null, content_hash: string}>
     */
    public function getAbsolutions(string $filePath): array;

    /**
     * Check if the file has changed since it was absolved.
     */
    public function hasChangedSinceAbsolution(string $filePath, string $commandmentClass, string $currentContent): bool;
}
