<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills;

use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Custom;
use JesseGall\CodeCommandments\Languages;
use JesseGall\CodeCommandments\Workspace;

/**
 * The briefing every agent reads — the block placed in a project's `AGENTS.md`, addressed to no
 * assistant in particular: the disciplines, the skills that teach them, the commands that find and
 * fix them. What is true of only ONE agent belongs on that agent
 * ({@see \JesseGall\CodeCommandments\Agents\Agent::instructions}). The prose is fixed; the two
 * skill lists come from {@see Catalog}, so adding or re-tiering a skill updates the briefing.
 */
final class Briefing
{
    /**
     * The canon's own block name, distinct from the one an agent's file carries — so that where a
     * project has made one file a link to the other, the two blocks sit side by side.
     */
    public const string BLOCK = 'code-commandments briefing';

    /**
     * @param  string|null  $project  the consumer root, so its OWN skills are briefed too
     */
    public static function render(?string $project = null, ?Languages $languages = null): string
    {
        // The project's OWN set, so a briefing never tells a reader to load a discipline for a
        // language they have said they do not write (#478).
        $languages ??= Languages::from(Config::load($project));

        $mandatory = self::bullets(Tier::Mandatory, $project, $languages);
        $keepInMind = self::bullets(Tier::KeepInMind, $project, $languages);
        $library = Workspace::LIBRARY;

        return <<<MD
        ## Skills — load before you work

        Code style in this project lives in the code-commandments skills, published to
        `{$library}/` and loaded **by the exact id in each bullet below** —
        `commandments-backend-<name>` / `commandments-frontend-<name>` (e.g.
        `commandments-backend-absence`). HOW you load one is your own business: some agents
        have a tool for it, some a `/`-command, some want the file read. The id is the same
        either way, and so is the rule — read the relevant skill before writing or reviewing
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

        **Stop conditions — when the user says "keep going until X".** Record it at once:
        `vendor/bin/commandments stop-condition "<condition>"`. While it stands you may not stop: every
        stop is held and sends you back to VERIFY the condition — actually run the command and
        read the output, never assume it holds because you did the work. Strike one off with
        `vendor/bin/commandments stop-condition met <n>` once it genuinely holds; if you are truly
        blocked, `vendor/bin/commandments stop-condition stuck` hands back to the user while keeping the
        condition in force. Never `stop-condition clear` to escape a condition you simply haven't met.

        **The same gate is where mid-work interjections go.** When the user speaks while you are
        already working, decide what their message IS: **steering** the work in hand (a correction,
        a change of approach, "while you're in there…") is done **now** — parking it is a way of not
        doing it. A **separate task**, one they deferred ("later", "when you're done"), or anything
        that would derail the phase you're on is **parked**: `vendor/bin/commandments stop-condition "<the
        task, as a statement you can verify>"`, then carry on with what you were doing. Unsure?
        Cheap and inside the current phase → do it; opens a new front → park it. A plan takes
        precedence over the gate, so parked work surfaces at `plan done` — exactly "later".
        Load the `commandments-stop-condition` skill for the discipline.

        When in doubt, load `commandments-backend-fix-at-the-source` and re-read it. It is the parent move
        behind every other skill.

        **Leave it cleaner than you found it — the gentleman's duty.** When you touch a
        file (or even read past a sin while working in it), fix it at the source per the
        rule above. Every finding on code you come across is yours to resolve.

        **The disciplines.** Each one is a skill, and each says in its own description WHEN to
        reach for it — the syntax you are about to write, the decision you are about to make. Load
        one when its subject comes up, and load it again rather than working from memory: a
        compaction drops instructions silently while leaving you the impression they are still
        there. A `judge` finding names the skill that fixes it, so you are never guessing.

        Load **`commandments-backend-fix-at-the-source`** before your first edit whatever else you
        do — every other discipline defers to it. And **`commandments`** is this whole list as a
        loadable skill, for when you want the map in front of you.

        **CONSTANTLY IN PLAY — these fire on ordinary, everyday code, so you will reach for them
        most:**

        {$mandatory}

        **ON CONTACT — load the moment the work touches the subject:**

        {$keepInMind}

        **Finding and fixing sins — the checklist workflow.** Run
        `vendor/bin/commandments judge src` ONCE — and **pass any path** to scope the
        scan: judge runs BOTH engines over whatever you point it at, so a path holding
        frontend sources (`judge resources/js`) is judged as the frontend
        — **Vue components and plain TypeScript alike** — and any subdirectory of your
        own tree scopes to that subtree. (Also `--skill=NAME` to scope to one group; `--branch`
        for files new/changed vs `main`; `--changes` for uncommitted changes.) A full scan
        is slow, so it writes the findings to a checklist — your session's
        `.commandments/sessions/<id>/sins/sins.md` (the run prints the exact path) — and
        that file, not repeated scans, is how you work:

        1. Open the checklist judge wrote. Each line is one sin: `file:line`, the scope, and
           the detector, grouped under the skill that teaches the fix.
        2. Go top to bottom, ONE line at a time: read that section's skill, fix the sin at
           the source, then **delete that line from the file.** Do not re-run judge, re-scan,
           or re-verify between fixes — the open checklist is your only source of truth.
        3. Work **wave by wave.** When the file is EMPTY, run judge again. If your fixes
           rippled into other files it writes a fresh worklist — a new wave; work it the same
           way. Repeat, judging ONLY between waves, until a run is clean and deletes the file.

        **Don't know what a finding MEANS? Ask.** A checklist line names its detector and
        nothing else, so when you do not recognise a rule — or are about to argue with one —
        run `vendor/bin/commandments info <sin>` before you touch the code. It prints what
        the rule flags, WHY it is a sin in the skill's own words, how it is fixed, a worked
        example, and the exact commands that act on it (fix it, find it, turn it off, report
        it). The name is matched leniently, so the detector name straight off the checklist
        works: `info ArrayBagDetector`, `info array-bag`, `info ArrayBag`. Add `--full` for
        the skill's whole principle. Guessing at a rule you have not read is how a finding
        gets "fixed" by silencing it.

        **Auto-fixable sins.** Some sins have a scribe that fixes them. The report
        advertises the command — typically `vendor/bin/commandments repent --repent=latest`
        (optionally `--sin=NAME` to fix just one). `--repent=latest` scopes repent to the
        last judge run's checklist, so it fixes exactly what was reported; review the diff
        with `--dry-run` first.

        **Write commandments of your OWN.** The shipped rules are not the ceiling. When
        this project has a discipline of its own — a convention you keep restating in
        review, a mistake that keeps coming back, anything the shipped set doesn't
        catch — it can become a rule that judges every file from then on. Scaffold it:

        `vendor/bin/commandments make <Name>` (add `--engine=frontend` for a rule over
        your frontend sources — a Vue component or a TypeScript module)

        That writes the three classes a commandment is made of — the skill that teaches
        it, the sin that names it, the detector that finds it — into
        `.commandments/custom/`, registers the detector in this project's config, and
        prints the rest of the process. The folder is committed like any other source:
        these are the project's rules. **Load the `commandments-writing-detectors`
        skill before you write one** — it lists the engine predicates that already
        exist (hand-rolling one that does is the usual first mistake) and teaches the
        probe-then-calibrate discipline that proves a detector fires on what you meant.
        Reach for this whenever the user asks for a new rule, check, or detector.

        **Scaffoldable sins.** A few sins are fixed by reaching for a generic helper the
        project may not have yet (e.g. a no-op invokable for a nullable callback). For
        those the report advertises `vendor/bin/commandments scaffold --sin=NAME`, which
        generates the helper into your source root with its namespace set. Scaffold the
        construct, then write the fix that uses it (`scaffold` creates the helper; `repent`
        fixes call sites).
        MD;
    }

    /**
     * One tier's bullets, the project's own skills named AS the project's — a rule it wrote is fixed
     * and edited in `.commandments/custom/`, never upstream, so the briefing says whose it is (#414).
     */
    private static function bullets(Tier $tier, ?string $project, Languages $languages): string
    {
        $bullet = static fn (Skill $skill): string => $skill->bullet()
            . (Custom::owns($skill, $project) ? ' _(this project\'s own — `.commandments/custom/`)_' : '');

        return implode("\n", array_map($bullet, Catalog::inTier($tier, $project, $languages)));
    }
}
