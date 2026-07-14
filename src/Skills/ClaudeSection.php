<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills;

/**
 * Renders the auto-managed "Skills — load before you work" block injected into a
 * consumer's CLAUDE.md. The prose is fixed; the two skill lists are generated from
 * {@see Catalog}, so adding or re-tiering a skill updates the briefing.
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

        Code style in this package lives in the code-commandments skills, installed under
        `.claude/skills/` and loaded **via the Skill tool by the exact id in each bullet
        below** — `commandments-backend-<name>` / `commandments-frontend-<name>` (e.g.
        `commandments-backend-absence`). They are the source of truth for every
        architecture/style decision — read the relevant one before writing or reviewing
        code. Two tiers.

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
          it gets fixed upstream instead of silently ignored, ALWAYS referencing the code:
          `vendor/bin/commandments report --detector=NAME --reason="…" --ref=PATH:LINE`
          (repeat `--ref` for EVERY file involved — a bug spanning files references each).
          Design-smell detectors REQUIRE a `--best-design="…"`: the cleanest design you can
          conceive for this code. It is the litmus — a report is valid ONLY if the flagged
          code already IS that design; if you can name anything cleaner, that design is the
          owed fix, so implement it and do not file. The reason may not hedge; a "correct,
          but…" is rejected. Never rationalise a real finding as "pre-existing baseline."
        - **A report is NOT a deferral.** A detector-report claims ONE thing: the flagged
          code is CORRECT under the architecture and the detector is wrong. If the finding
          is right and an honest fix exists, you MUST implement it — however far it
          cascades: many call sites, a data migration, a refactor that ripples. That
          breadth IS the point; it is the debt surfacing, not a reason to file. The moment
          your report would contain "the honest fix is real, but…" — stop: everything
          after "but" is scope you own. Cost, timing, "needs its own change", or "risks
          breaking existing data" are never grounds for a report; they are the work.

        **You are ENCOURAGED to make the tool better — not just when a finding is clearly
        wrong.** Surface every improvement idea: a rule that's missing or should catch more,
        a false positive, OR a `repent` auto-fix that did the wrong thing or left a rough
        edge. Two channels, and using them is expected, not exceptional:

        - `vendor/bin/commandments report --reason="…" --ref=PATH:LINE [--ref=…]` — a bug or
          false positive (a wrong finding, or a broken/incorrect `repent` result). A broken
          auto-fix is itself a bug: report it, referencing both the source and the bad output.
        - `vendor/bin/commandments feature-request --title="…" --reason="…"` — a new or
          changed rule.

        Reporting false positives, flagging bad auto-fixes, and requesting rules is how the
        disciplines get sharper — do it whenever something is wrong or could be better, don't
        just work around it.

        **Frozen files — a file that is deliberately immutable.** A few files must not
        change even though they carry sins: a frozen graph migration whose body mirrors
        its siblings on purpose, a snapshot committed for the record, generated code
        checked into the tree. Mark such a file frozen:
        `vendor/bin/commandments freeze <path>` (or add `#[Frozen]` / an `@frozen`
        docblock tag by hand). A frozen file is still **scanned** — the call graph,
        provenance and type resolution read it, so cross-file findings elsewhere stay
        correct — but it is never a **target**: it is never flagged, and a repenter
        never rewrites it (a cross-file fix whose edits would touch a frozen file is
        dropped whole, never half-applied). Lift it with
        `vendor/bin/commandments unfreeze <path>`. **Freeze only what is genuinely
        immutable — never to silence a sin you could fix.** A real finding you disagree
        with is a `report`; a rule you want off is `disable`; freezing is for files that
        by their nature cannot move.

        When in doubt, load `commandments-backend-fix-at-the-source` and re-read it. It is the parent move
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
        `vendor/bin/commandments judge src` ONCE — and **pass any path** to scope the
        scan: `judge resources/js` judges the **Vue frontend** (judge runs both engines),
        `judge app/Http` a subtree. (Also `--skill=NAME` to scope to one group; `--branch`
        for files new/changed vs `main`; `--changes` for uncommitted changes.) A full scan
        is slow, so it writes the findings to a checklist — your session's
        `.commandments/sessions/<id>/sins.md` (the run prints the exact path) — and
        that file, not repeated scans, is how you work:

        1. Open the checklist judge wrote. Each line is one sin: `file:line`, the scope, and
           the detector, grouped under the skill that teaches the fix.
        2. Go top to bottom, ONE line at a time: read that section's skill, fix the sin at
           the source, then **delete that line from the file.** Do not re-run judge, re-scan,
           or re-verify between fixes — the open checklist is your only source of truth.
        3. Work **wave by wave.** When the file is EMPTY, run judge again. If your fixes
           rippled into other files it writes a fresh worklist — a new wave; work it the same
           way. Repeat, judging ONLY between waves, until a run is clean and deletes the file.

        **Auto-fixable sins.** Some sins have a scribe that fixes them. The report
        advertises the command — typically `vendor/bin/commandments repent --repent=latest`
        (optionally `--sin=NAME` to fix just one). `--repent=latest` scopes repent to the
        last judge run's checklist, so it fixes exactly what was reported; review the diff
        with `--dry-run` first.

        **Scaffoldable sins.** A few sins are fixed by reaching for a generic helper the
        project may not have yet (e.g. a no-op invokable for a nullable callback). For
        those the report advertises `vendor/bin/commandments scaffold --sin=NAME`, which
        generates the helper into your source root with its namespace set. Scaffold the
        construct, then write the fix that uses it (`scaffold` creates the helper; `repent`
        fixes call sites).
        MD;

        return self::BEGIN . "\n" . $body . "\n" . self::END;
    }

    private static function bullets(Tier $tier): string
    {
        return implode("\n", array_map(static fn (Skill $skill): string => $skill->bullet(), Catalog::inTier($tier)));
    }
}
