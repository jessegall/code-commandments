<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\PhpTypes\T_String;

/**
 * The SINGLE source of truth for the package-owned `## Code Commandments` section
 * of a consumer's CLAUDE.md. Twin of {@see ClaudeHooksInstaller}: one template
 * parameterized by runner (artisan `:` vs standalone space) so the two wirings
 * can't drift, and `sync` re-asserts it on every update.
 *
 * The owned region is fenced by sentinel markers so a replace touches ONLY our
 * block and never a consumer subsection nested under the heading. The splice uses
 * substr_replace (NOT preg_replace, whose `$1`/`\1` in the replacement would
 * corrupt a section body containing `$`/`\`). Entry points differ:
 *  - {@see self::install()} (install-hooks / init) may CREATE / APPEND / REPLACE.
 *  - {@see self::reassert()} (sync) ONLY replaces an existing section, else no-op —
 *    so a consumer who deleted the section is never force-fed it, and an update
 *    never imposes the section on a CLAUDE.md that never had it.
 * Both are merge-safe (skip a file with git conflict markers) and idempotent
 * (no write when the result equals the current content).
 */
final class ClaudeMdInstaller
{
    public const BEGIN = '<!-- code-commandments:begin -->';
    public const END = '<!-- code-commandments:end -->';

    public const STATUS_CREATED = 'created';
    public const STATUS_REPLACED = 'replaced';
    public const STATUS_APPENDED = 'appended';
    public const STATUS_UNCHANGED = 'unchanged';
    public const STATUS_NO_SECTION = 'no_section';
    public const STATUS_SKIPPED_CONFLICT = 'skipped_conflict';
    public const STATUS_WRITE_FAILED = 'write_failed';
    public const STATUS_REMOVED = 'removed';

    /**
     * The full owned block (sentinels + heading + body) for a runner.
     *
     * @param  array{0: string, 1: string}  $runner  self::ARTISAN / self::STANDALONE on ClaudeHooksInstaller
     */
    public static function section(array $runner): string
    {
        return self::BEGIN . T_String::NEWLINE . self::body($runner[0], $runner[1]) . T_String::NEWLINE . self::END;
    }

    /**
     * Create / append / replace the section — the opt-in install moment. The
     * runner is detected from the project, so the artisan and standalone entry
     * points write identical content.
     */
    public static function install(string $basePath): string
    {
        $path = rtrim($basePath, '/') . '/CLAUDE.md';
        $block = self::section(ClaudeHooksInstaller::runnerFor($basePath));

        if (! is_file($path)) {
            return @file_put_contents($path, $block . T_String::NEWLINE) === false
                ? self::STATUS_WRITE_FAILED
                : self::STATUS_CREATED;
        }

        $content = (string) @file_get_contents($path);

        if (self::hasConflictMarkers($content)) {
            return self::STATUS_SKIPPED_CONFLICT;
        }

        $replaced = self::replaceInto($content, $block);

        if ($replaced === null) {
            // No section yet — append it.
            $new = rtrim($content, T_String::NEWLINE) . T_String::PARAGRAPH . $block . T_String::NEWLINE;

            return @file_put_contents($path, $new) === false ? self::STATUS_WRITE_FAILED : self::STATUS_APPENDED;
        }

        if ($replaced === $content) {
            return self::STATUS_UNCHANGED;
        }

        return @file_put_contents($path, $replaced) === false ? self::STATUS_WRITE_FAILED : self::STATUS_REPLACED;
    }

    /**
     * Re-assert on update: replace the section in place ONLY when one already
     * exists; never create or append. No-op (STATUS_NO_SECTION) otherwise. The
     * runner is detected from the project.
     */
    public static function reassert(string $basePath): string
    {
        $path = rtrim($basePath, '/') . '/CLAUDE.md';

        if (! is_file($path)) {
            return self::STATUS_NO_SECTION;
        }

        $content = (string) @file_get_contents($path);

        if (self::hasConflictMarkers($content)) {
            return self::STATUS_SKIPPED_CONFLICT;
        }

        $replaced = self::replaceInto($content, self::section(ClaudeHooksInstaller::runnerFor($basePath)));

        if ($replaced === null) {
            return self::STATUS_NO_SECTION;
        }

        if ($replaced === $content) {
            return self::STATUS_UNCHANGED;
        }

        return @file_put_contents($path, $replaced) === false ? self::STATUS_WRITE_FAILED : self::STATUS_REPLACED;
    }

    /**
     * Remove the package-owned section entirely — the deliberate `profile disabled`
     * act that makes the agent unaware the package judges. Strips the sentinel-fenced
     * (or legacy-heading) region and collapses the surrounding blank lines; leaves
     * the rest of CLAUDE.md untouched. No-op when there is no section.
     */
    public static function remove(string $basePath): string
    {
        $path = rtrim($basePath, '/') . '/CLAUDE.md';

        if (! is_file($path)) {
            return self::STATUS_NO_SECTION;
        }

        $content = (string) @file_get_contents($path);

        if (self::hasConflictMarkers($content)) {
            return self::STATUS_SKIPPED_CONFLICT;
        }

        $stripped = self::stripSection($content);

        if ($stripped === null) {
            return self::STATUS_NO_SECTION;
        }

        if ($stripped === $content) {
            return self::STATUS_UNCHANGED;
        }

        return @file_put_contents($path, $stripped) === false ? self::STATUS_WRITE_FAILED : self::STATUS_REMOVED;
    }

    /**
     * Cut the owned region out of $content (sentinel-fenced, else legacy heading),
     * collapsing the blank lines left behind. Null when there is no owned section.
     */
    private static function stripSection(string $content): ?string
    {
        $begin = strpos($content, self::BEGIN);
        $end = strpos($content, self::END);

        if ($begin !== false && $end !== false && $end > $begin) {
            $end += strlen(self::END);

            return self::join(substr($content, 0, $begin), substr($content, $end));
        }

        if (preg_match('/^## Code Commandments\b/m', $content, $m, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $start = $m[0][1];
        $rest = substr($content, $start + 1);

        if (preg_match('/^## /m', $rest, $m2, PREG_OFFSET_CAPTURE) === 1) {
            $tail = substr($content, $start + 1 + $m2[0][1]);

            return self::join(substr($content, 0, $start), $tail);
        }

        return self::join(substr($content, 0, $start), T_String::empty());
    }

    /**
     * Re-join the text before and after the removed section, collapsing the gap to
     * a single blank line (or a clean EOF).
     */
    private static function join(string $before, string $after): string
    {
        $before = rtrim($before, T_String::NEWLINE);
        $after = ltrim($after, T_String::NEWLINE);

        if (T_String::isEmpty($before)) {
            return T_String::isEmpty($after) ? T_String::empty() : $after . T_String::NEWLINE;
        }

        return T_String::isEmpty($after) ? $before . T_String::NEWLINE : $before . T_String::PARAGRAPH . $after . T_String::NEWLINE;
    }

    /**
     * Splice $block over the existing owned region, or null when there is none.
     * Prefers the sentinel-fenced span; falls back to a legacy heading span
     * (`## Code Commandments` up to the next top-level `## `), self-upgrading it to
     * the fenced form. substr_replace inserts $block LITERALLY — no backreference
     * hazards.
     */
    public static function replaceInto(string $content, string $block): ?string
    {
        $begin = strpos($content, self::BEGIN);
        $end = strpos($content, self::END);

        if ($begin !== false && $end !== false && $end > $begin) {
            $end += strlen(self::END);

            return substr_replace($content, $block, $begin, $end - $begin);
        }

        // Legacy: locate the heading and the next top-level heading after it.
        if (preg_match('/^## Code Commandments\b/m', $content, $m, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $start = $m[0][1];
        $rest = substr($content, $start + 1);

        if (preg_match('/^## /m', $rest, $m2, PREG_OFFSET_CAPTURE) === 1) {
            $span = 1 + $m2[0][1]; // up to (not including) the next heading
            $tail = substr($content, $start + $span);

            return substr($content, 0, $start) . $block . T_String::PARAGRAPH . ltrim($tail, T_String::NEWLINE);
        }

        // Section runs to EOF.
        return substr($content, 0, $start) . $block . T_String::NEWLINE;
    }

    private static function hasConflictMarkers(string $content): bool
    {
        return preg_match('/^(<{7}|={7}|>{7})/m', $content) === 1;
    }

    /**
     * The settings.json `instructions` block — the SAME content for both runners,
     * centralized here so the artisan and standalone wirings can't diverge (audit
     * #16 REPORT-IS-NOT-A-DODGE drift, #19 absolve-reason drift).
     *
     */
    public static function settingsInstructions(string $basePath): string
    {
        $runner = ClaudeHooksInstaller::runnerFor($basePath);
        $r = $runner[0] . $runner[1];

        $pilgrimage = "{$r}pilgrimage";
        $next = "{$r}next";
        $judge = "{$r}judge";
        $absolveAll = "{$r}absolve --all --reason=\"accept pre-existing backlog\"";
        $absolveAt = "{$r}absolve --at=path:line --prophet=NAME --reason=\"why it does not apply\"";
        $scripture = "{$r}scripture --prophet=NAME";
        $reportFull = "{$r}report --at=path:line --reason=\"what is wrong\"";
        $repent = "{$r}repent";
        $autofix = "{$r}autofix";
        $reportShort = "{$r}report --at=path:line --reason=\"…\"";
        $featureReq = "{$r}report --feature-request --title=\"…\" --reason=\"…\"";

        return <<<INSTRUCTIONS
This project uses Code Commandments to enforce coding standards.

IMPORTANT: The git pre-commit hook (`judge --staged`) BLOCKS a commit until
every finding on the staged files is resolved — sins fixed, and each advisory
finding fixed OR absolved with a reason. Advisories carry a rubric (use
judgment) but are NOT ignorable at commit time.

THE GUIDED WORKFLOW (use this): `{$pilgrimage}` to begin, then `{$next}` to
advance. It walks the doctrines pillar by pillar, ONE PROPHET at a time, showing
that prophet's full scripture and EVERY location it fires at.
READ EACH OUTPUT IN FULL — never head/tail/truncate it, or you will miss
locations and leave the pillar unresolved. For the shown prophet, resolve EVERY
location, then run `{$next}` — it RE-CHECKS that prophet and refuses to advance
until it is clean (forward-only — it never loops back to a passed prophet). For
each location do exactly one:
  - FIX it (follow the scripture); OR
  - if it is an advisory finding whose rubric does not apply here, absolve it
    WITH A REASON: `{$absolveAt}`.
Sins are imperative and cannot be absolved — they must be fixed. Do NOT bulk
`{$repent}` or `{$judge}` to "start" — while the walk runs they are LOCKED. On an
[AUTO-FIXABLE] prophet, run `{$autofix}` to fix THAT prophet in place, then
`{$next}`. `{$judge}` (no flag) is the whole-codebase audit, for when no walk runs.
To accept a large pre-existing backlog once (only NEW advisories surface after; sins
still block), baseline it: `{$absolveAll}`.

OWN EVERY SIN YOU ENCOUNTER: a sin is a sin regardless of who wrote it. If
judge surfaces a sin — in your own changes OR pre-existing in a file you are
working in — you handle it. Fix it (sins cannot be absolved), or for an
advisory warning whose rubric genuinely does not apply, absolve it with a
reason. "I didn't cause this" is NEVER a reason to leave a finding in place.
Be a gentleman: leave every file you touch righteous.

REQUIRED: Always read the rule before fixing. `{$next}` prints the prophet's
full scripture inline; for the same rule on its own run
`{$scripture}`. Each advisory carries an
APPLY-WHEN / LEAVE-WHEN rubric — use judgment — but a staged commit is BLOCKED
until every one is fixed or absolved with a reason. Never leave one untouched.

PHASED-COMMIT WORKFLOW (for any multi-step change, all in ONE pull request):
  1. Implement ONE phase.
  2. Walk `{$pilgrimage}` then `{$next}` until clean —
     fix every sin (and address each advisory).
  3. Commit and push that phase.
  4. Move to the next phase and repeat.
This keeps every commit righteous and each phase reviewable on its own.

REPORT PROPHET PROBLEMS YOURSELF — proactively, without being asked. The
moment you hit any of these, file a report (do not just absolve, work around,
or move on):
  - a genuine false positive (flagged code that is actually fine);
  - a wrong/ill-fitting rule;
  - a prophet BUG: tagged [AUTO-FIXABLE] but `repent` no-ops or fails, a crash,
    a misleading or incorrect message, a bad auto-fix.
  - a SCAFFOLDING bug: the generated support classes (Option, Union, Resolver,
    NullCallable, the Predicate kernel, …) raise PHPStan / static-analysis
    errors or do not compile — that is a package defect, report it too (use the
    scaffold class as --prophet, e.g. --prophet=Option).
  - a PHP-TYPES bug: the `jessegall/php-types` package (T_String, T_Array,
    T_Json, Option, …) misbehaves — the commandments team also maintains
    php-types, so report those here too (use the type as --prophet, e.g.
    --prophet=T_String).
  {$reportFull}
This files a GitHub issue another session picks up and fixes. Reporting is
part of the job — it is how the prophets improve.

REPORT IS NOT A DODGE. Report only a GENUINELY wrong finding — a false
positive, an ill-fitting rule, or a prophet bug. A rule you understand but
would rather not follow is NOT a report: fix the code. "I disagree" is not
"the prophet is wrong."

TO PROPOSE A NEW RULE OR FEATURE (not a wrong finding), use
`{$featureReq}` — it files an enhancement
issue, needs no locator, and records no absolution.

REPORTING A WRONG FINDING QUIETS IT — until the issue is answered. Pass the
finding's locator so the report records a report-linked absolution:
  {$reportShort}
The flagged finding (even a SIN) then goes quiet and STAYS quiet across commits
— it survives the post-commit reset, so you can commit. `report` dedups: it
will not file the same finding twice. When the issue is answered, the
absolution lifts (session-start `reports --check` detects the close): a real
false positive is gone after `composer update`; a sin closed as "works as
intended" RE-BLOCKS, and you must fix it. So a wrong report self-corrects — it
buys quiet now, not a permanent pass.

COMMANDS:
  {$judge}              # Audit the whole codebase
  {$pilgrimage}         # GUIDED: begin the forward-only walk (one prophet at a time)
  {$next}               # advance the walk (verify-before-advance; read output IN FULL)
  {$repent}             # Auto-fix the [AUTO-FIXABLE] ones
  {$absolveAt}  # absolve a genuine advisory false positive
  {$reportShort}  # report a wrong finding / prophet bug
  {$scripture}  # Full rule for a prophet
INSTRUCTIONS;
    }

    private static function body(string $binary, string $sep): string
    {
        $r = $binary . $sep; // e.g. "php artisan commandments:" or "vendor/bin/commandments "

        $judgeNextGit = "{$r}judge --next --git";
        $absolveAll = "{$r}absolve --all --reason=\"accept pre-existing backlog\"";
        $absolveReason = "{$r}absolve --fingerprint=<hash> --reason=\"why it does not apply\"";
        $judgeGit = "{$r}judge --git";
        $judgeNext = "{$r}judge --next";
        $absolveH = "{$r}absolve --fingerprint=H --reason=\"…\"";
        $repent = "{$r}repent";
        $autofix = "{$r}autofix";
        $reportShort = "{$r}report --at=path:line --reason=\"…\"";
        $scripture = "{$r}scripture --prophet=NAME";
        $reportFull = "{$r}report --at=path:line --reason=\"why\"";

        return <<<MARKDOWN
## Code Commandments

This project enforces coding standards via the Code Commandments package.

**IMPORTANT: The git pre-commit hook (`judge --staged`) BLOCKS a commit until every finding on the staged files is resolved — sins fixed, and each warning fixed OR absolved with a reason. Warnings carry an APPLY-WHEN / LEAVE-WHEN rubric (use judgment), but they are NOT ignorable at commit time.**

**REQUIRED: Always read the rule before fixing. `judge --next` shows the rubric inline; `{$scripture}` shows the full scripture. The detailed description is the authoritative specification — follow it exactly.**

### The guided workflow (use this)

```bash
{$judgeNextGit}   # walk findings in YOUR changes
```

**Scope to your own changes with `--git`** so you are not handed the whole repo's pre-existing backlog. (Plain `judge --next` walks the entire codebase.) If a large pre-existing backlog is in the way, baseline it once — this absolves every current advisory finding so only NEW ones surface (sins still block):

```bash
{$absolveAll}
```

It shows exactly **one finding at a time** with its full rule inline — so nothing gets lost in a wall of output. For each finding, do exactly one of:

- **Fix it**, then run `judge --next` again for the next finding; or
- If it is an advisory **warning** whose rubric does not apply here, **absolve it with a reason**:
  `{$absolveReason}`.

Sins are imperative and **cannot be absolved** — they must be fixed. Warnings are **advisory**: each carries an APPLY-WHEN / LEAVE-WHEN rubric. **Default to FIXING a warning.** Absolve it only when the rubric's LEAVE-WHEN genuinely applies, and say why — absolve is not a dismiss button, and the post-commit reset wipes absolutions anyway, so a dodged warning comes back next phase. Never leave a warning untouched.

### Own every sin you encounter

A sin is a sin regardless of who wrote it. If `judge` surfaces a sin — whether in your own changes or **pre-existing** in a file you are working in — **you handle it**: fix it (sins cannot be absolved), or for an advisory warning whose rubric genuinely does not apply, absolve it with a reason. **"I didn't cause this" is never a reason to leave a finding in place.** Be a gentleman: leave every file you touch righteous.

### Phased-commit workflow (multi-step changes, one PR)

1. Implement **one phase**.
2. Run `{$judgeGit}`, then `--next` until clean — fix every sin and address each warning.
3. **Commit and push** that phase.
4. Move to the next phase and repeat.

Every commit stays righteous and each phase is reviewable on its own.

### Commands

```bash
{$judgeGit}        # Check changed files
{$judgeNext}       # GUIDED: one finding at a time
{$absolveH}  # warnings only
{$repent}             # Auto-fix [AUTO-FIXABLE] sins
{$reportShort}  # Report a false positive
{$scripture}  # Full rule for a prophet
```

**Hit a prophet problem? Report it yourself, proactively.** A false positive, a rule that does not fit, a prophet bug (tagged [AUTO-FIXABLE] but `repent` no-ops/fails, a crash, a wrong message), a **scaffolding bug** (the generated support classes — Option, Union, Resolver, NullCallable, the Predicate kernel — raise PHPStan/static-analysis errors or don't compile), OR a **php-types bug** (`jessegall/php-types`: T_String, T_Array, Option, … — the commandments team also maintains php-types) — do not just absolve or work around it: `{$reportFull}` files a GitHub issue another session fixes (for a scaffold or php-types defect, pass the class via `--at`, or name it with `--prophet=Option` / `--prophet=T_String`). **Report is not a dodge** — only a *genuinely* wrong finding qualifies: a false positive, an ill-fitting rule, or a prophet bug. A rule you understand but would rather not follow is NOT a report: fix the code. "I disagree" is not "the prophet is wrong."

**To propose a NEW rule or feature** (not a wrong finding), use `{$r}report --feature-request --title="…" --reason="…"` — it files an enhancement issue, needs no `--at`, and records no absolution.

**Reporting a wrong finding quiets it until the issue is answered.** Pass the finding's locator — `{$r}report --at=path:line --reason="why"` — and the finding (even a **sin**) goes quiet and **stays quiet across commits** (it survives the post-commit reset, so you can commit; `report` will not file a duplicate). When the issue is answered (`reports --check` at session start detects the close), the absolution **lifts**: a real false positive is gone after `composer update`; a sin closed as "works as intended" **re-blocks** and you must fix it.

### Plan loop (only if `commandments.hooks.plan_loop` is enabled)

When the opt-in plan-loop is on, an approved plan auto-continues phase by phase. The manual controls:

- `sh .claude/hooks/plan-start.sh` — ARM the loop (also done automatically when a plan is approved).
- `sh .claude/hooks/plan-release.sh "<reason>"` — the ONLY sanctioned way to release it; it REFUSES a non-blocker reason (a long turn, compaction, wanting to checkpoint), so the loop only ends on a genuine blocker or when the plan is DONE.
- The runtime marker is `.claude/plan-active`. **Never delete it by hand** — `guard-plan-marker.sh` blocks that; release via `plan-release.sh` instead. A genuinely new session clears a stale marker automatically.
MARKDOWN;
    }
}
