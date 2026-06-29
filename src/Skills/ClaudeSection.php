<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills;

/**
 * Renders the auto-managed "Skills — load before you work" block injected into a
 * consumer's CLAUDE.md. The prose is fixed; the two skill lists are generated from
 * {@see Skills}, so adding or re-tiering a skill updates the briefing.
 */
final class ClaudeSection
{
    public const string BEGIN = '<!-- BEGIN: code-commandments skills (auto-managed — do not edit between these markers) -->';

    public const string END = '<!-- END: code-commandments skills -->';

    public static function render(): string
    {
        $mandatory = self::bullets(Tier::Mandatory);
        $keepInMind = self::bullets(Tier::KeepInMind);

        $body = <<<MD
        ## Skills — load before you work

        Code style in this package lives in the code-commandments skills under
        `.claude/skills/commandments-*`. Two tiers. They are the source of truth for
        every architecture/style decision — read the relevant one (via the Skill tool)
        before writing or reviewing code.

        ### ⚠️ THE MOST IMPORTANT RULE — TRACE TO THE SOURCE

        **Every sin has an origin. Fix it THERE, never where it surfaces.** A finding is a
        symptom; before you change a line, trace upstream to where the bad value, missing
        type, or wrong shape is BORN, and fix it at that origin. Then the symptom — and
        usually several others you hadn't found — disappears on its own.

        This is the rule the detectors exist to serve. Honour it on EVERY fix:

        - Do NOT silence the symptom. A `?? default`, a cast, a null-check, a wrapper, a
          constructor override, a try/catch, or removing a type to satisfy a detector is
          laundering the problem, not solving it — and it grows the code while hiding the
          smell. That is the wrong move even when the real fix is awkward to reach.
        - Ask "where does this value/shape come from?" and walk back until you reach the
          birthplace. Fix is at the birthplace. If the honest fix touches many call sites,
          touch them — that breadth IS the bug surfacing, not a reason to wrap it.
        - If a finding is a genuine false positive, say so and why — and **report it** so
          it gets fixed upstream instead of silently ignored:
          `vendor/bin/commandments report --detector=NAME --reason="…" --file=PATH --line=N`.
          Never rationalise a real finding as "pre-existing baseline."

        **See a rule that's missing, or one that should catch more?** Propose it:
        `vendor/bin/commandments feature-request --title="…" --reason="…"`. Reporting
        false positives and requesting rules is how the disciplines get sharper — do it
        whenever a finding is wrong or a gap is real, don't just work around it.

        When in doubt, load `fix-at-the-source` and re-read it. It is the parent move
        behind every other skill.

        **Leave it cleaner than you found it — the gentleman's duty.** When you touch a
        file (or even read past a sin while working in it), fix it at the source per the
        rule above. Every finding on code you come across is yours to resolve.

        **Do the writing yourself — never delegate edits to a subagent.** You may
        dispatch subagents ONLY for READ-ONLY work: research, codebase exploration,
        search. EVERY write — every file edit, creation, or rewrite — must be done by
        YOU directly, never handed to a spawned agent. A subagent holds these
        disciplines and this project's context more shallowly than you do, so a
        delegated edit is how violations slip in. Read-only fan-out is welcome; the
        writing is yours alone.

        **MANDATORY LOAD — load these at the start of every coding session, before you
        explore-to-plan or edit a single line** (via the Skill tool):

        {$mandatory}

        Do not start work without all of them loaded.

        **KEEP IN MIND — load the moment the work touches them:**

        {$keepInMind}

        **Finding and fixing sins — the checklist workflow.** Run
        `vendor/bin/commandments judge src` ONCE (add `--skill=NAME` to scope to one
        group; add `--branch` to judge only the files new/changed on your branch vs
        `main`, or `--changes` for just your uncommitted working-tree changes). A full
        scan is slow, so it writes the findings to a checklist file —
        `.commandments/sins.md` — and that file, not repeated scans, is how you work:

        1. Open `.commandments/sins.md`. Each line is one sin: `file:line`, the scope, and
           the detector, grouped under the skill that teaches the fix.
        2. Go top to bottom, ONE line at a time: read that section's skill, fix the sin at
           the source, then **delete that line from the file.** Do not re-run judge between
           fixes — the open checklist is your source of truth.
        3. When the file is empty, run judge once more to confirm (a clean run deletes it).
        MD;

        return self::BEGIN . "\n" . $body . "\n" . self::END;
    }

    private static function bullets(Tier $tier): string
    {
        return implode("\n", array_map(static fn (Skill $skill): string => $skill->bullet(), Skills::inTier($tier)));
    }
}
