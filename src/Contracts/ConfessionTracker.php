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

    /**
     * Absolve a single finding, identified by its content fingerprint.
     * Absolution lasts only until the flagged code changes (which yields a
     * new fingerprint that is no longer recorded here).
     */
    public function absolveFinding(string $fingerprint, ?string $reason = null): void;

    /**
     * Check whether a specific finding fingerprint has been absolved —
     * whether by an ordinary absolution or a report-linked one.
     */
    public function isFindingAbsolved(string $fingerprint): bool;

    /**
     * Record a report-linked absolution: the finding was reported as wrong
     * (false positive / wrong rule / prophet bug) and stays absolved until the
     * upstream issue is answered. Unlike an ordinary finding absolution this
     * SURVIVES the post-commit reset; it is released by `releaseReportedFinding`
     * when `reports --check` sees the issue close.
     */
    public function reportFinding(string $fingerprint, ?string $reason = null, ?int $issue = null, ?string $repo = null): void;

    /**
     * Whether a finding fingerprint has a report-linked absolution.
     */
    public function isFindingReported(string $fingerprint): bool;

    /**
     * Drop a report-linked absolution so the finding resurfaces (called when
     * the upstream issue is answered — fixed or closed as wontfix).
     */
    public function releaseReportedFinding(string $fingerprint): void;

    /**
     * Every report-linked absolution, keyed by fingerprint.
     *
     * @return array<string, array{reported_at: string, reason: string|null, issue: int|null, repo: string|null}>
     */
    public function reportedFindings(): array;

    /**
     * Record that a finding fingerprint was encountered live in this run.
     * Used by garbage collection to drop stale absolutions whose findings
     * no longer exist.
     */
    public function markFindingSeen(string $fingerprint): void;

    /**
     * Drop every finding absolution whose fingerprint was not marked seen
     * this run. Only safe to call after a complete scan — a narrowed scan
     * (--file/--git/--path) does not see every finding.
     *
     * @return int Number of stale absolutions removed
     */
    public function gcUnseenFindings(): int;

    /**
     * Remove every finding absolution. Used by the post-commit reset so
     * absolutions never silently persist across commits.
     *
     * @return int Number of absolutions removed
     */
    public function clearFindingAbsolutions(): int;
}
