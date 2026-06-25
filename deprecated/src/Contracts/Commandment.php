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
     * Prophet classes that are the likely ROOT CAUSE of this commandment's
     * findings. When such a finding is produced, the engine may check whether
     * one of these causes applies in the same region — to defer to it, annotate
     * the symptom with a root-cause hint (even under `--prophet=` filtering, when
     * the cause prophet did not run), or block an auto-fix that would launder the
     * cause. The inverse, symptom-side declaration of `supersedes()`.
     *
     * Declared centrally in {@see \JesseGall\CodeCommandments\Support\RootCauseMap}
     * — do not hand-author both directions on a prophet.
     *
     * @return list<class-string>
     */
    public function rootCauses(): array;

    /**
     * Get the full scripture (detailed explanation) of this commandment.
     */
    public function detailedDescription(): string;

    /**
     * The slug of the Claude Code skill that teaches this prophet's subject —
     * the on-demand "how to do it right" playbook a finding points at, or null
     * when no skill backs it. Derived from the skill catalogue, so the pointer
     * and the catalogue never drift.
     */
    public function skill(): ?string;

    /**
     * Fully-qualified class names this commandment must never judge — the very
     * primitives it recommends as the fix (its configured `Option` / `Union` /
     * etc.). A file that DECLARES one of these classes is skipped for this
     * prophet, so the rule never flags its own sanctioned solution. Matched by
     * FQCN (read from config), so a domain class that merely shares the short
     * name (e.g. `App\Models\Option`) is still judged.
     *
     * @return list<class-string>
     */
    public function exemptClasses(): array;

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
