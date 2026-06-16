<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Contracts;

use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;

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
     * The applicability rubric for a warning-emitting (advisory) prophet:
     * when to apply, when to leave it, and the default stance when unsure.
     *
     * Returns null for imperative prophets (pure sins) — there is no
     * judgment to guide.
     */
    public function advisory(): ?Advisory;

    /**
     * The structural altitude of this commandment, used to order findings
     * (most root-cause first) when they are resolved one at a time.
     */
    public function tier(): Tier;

    /**
     * Prophet classes whose findings this commandment supersedes. A
     * superseded finding is deferred while one of this prophet's findings
     * is unresolved in the same region — fixing the root cause often makes
     * the symptom disappear.
     *
     * @return list<class-string>
     */
    public function supersedes(): array;

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

    /**
     * Get paths that should be excluded from this commandment.
     *
     * @return array<string>
     */
    public function getExcludedPaths(): array;
}
