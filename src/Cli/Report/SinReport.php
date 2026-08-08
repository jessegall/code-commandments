<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Report;


use JesseGall\CodeCommandments\Finding;
use JesseGall\CodeCommandments\Cli\Scaffold;
use JesseGall\CodeCommandments\Skills\Skill;
/**
 * Renders the kept findings two ways: the coloured console report (grouped by the
 * skill that fixes each sin) and the Markdown checklist the agent prunes line by
 * line. Findings are grouped by skill and ordered by `file:line`, so the output is
 * identical no matter how the detector workers interleaved.
 */
final class SinReport
{
    /**
     * How many sibling occurrences a recurrence finding names before it just says "+N more".
     */
    private const int TWINS_SHOWN = 3;

    /**
     * Said once per group that holds one, in both renderings — a project-local rule's finding is the
     * project's own business end to end: it teaches, fires and is CORRECTED here (#414).
     */
    private const string CUSTOM_NOTE = 'Rules marked `(custom)` are THIS project\'s own, from '
        . '.commandments/custom/ — a wrong one is fixed there, never reported upstream.';

    /**
     * @var array<string, list<Finding>>
     */
    private array $bySkill;

    private int $total;

    /**
     * @param  list<Finding>  $findings
     * @param  array<string, string>  $fixable  sin name => the `repent` command that fixes it (scope included)
     * @param  array<string, string>  $scaffoldable  sin name => the `scaffold` command that generates the helper its fix uses
     */
    public function __construct(private readonly string $path, array $findings, private readonly array $fixable = [], private readonly array $scaffoldable = [])
    {
        $bySkill = [];

        foreach ($findings as $finding) {
            $bySkill[$finding->skill][] = $finding;
        }

        ksort($bySkill);

        foreach ($bySkill as $skill => $group) {
            // A TOTAL order — location, then detector, then scope — so two findings at
            // the same file:line break their tie deterministically, and the report is
            // byte-identical no matter what order the (parallel) workers drained in.
            usort($group, static fn (Finding $a, Finding $b): int =>
                strnatcmp($a->location, $b->location)
                    ?: strcmp($a->detector, $b->detector)
                    ?: strcmp($a->scope, $b->scope));
            $bySkill[$skill] = $group;
        }

        $this->bySkill = $bySkill;
        $this->total = count($findings);
    }

    public function isEmpty(): bool
    {
        return $this->total === 0;
    }

    public function total(): int
    {
        return $this->total;
    }

    /**
     * The coloured, grouped console report.
     */
    public function console(): string
    {
        $lines = [];

        foreach ($this->bySkill as $skill => $findings) {
            $lines[] = "\n\033[1;33m{$skill}\033[0m  (" . count($findings) . ')';
            $lines[] = "  \033[1;36m↳ " . Skill::loadInstruction($skill) . "\033[0m";
            $lines[] = "    \033[2mDon't fix from memory. If you think it's already loaded, assume it is NOT — a compaction may have dropped it — and load it again to be sure.\033[0m";

            if ($this->hasCustom($findings)) {
                $lines[] = "  \033[2m↳ " . self::CUSTOM_NOTE . "\033[0m";
            }

            foreach ($findings as $finding) {
                $location = $this->relative($finding->location);
                $lines[] = "  \033[36m{$location}\033[0m  {$finding->scope}  \033[2m[{$finding->rule()}]\033[0m";

                if ($finding->twins !== []) {
                    $lines[] = "    \033[2m↳ same shape as: " . $this->twins($finding) . "\033[0m";
                }
            }

            foreach ($this->fixCommands($findings) as $command) {
                $lines[] = "  \033[32m↳ auto-fixable: {$command}\033[0m";
            }

            foreach ($this->scaffoldCommands($findings) as $command) {
                $lines[] = "  \033[32m↳ scaffold the helper: {$command}\033[0m";
            }
        }

        $skills = count($this->bySkill);
        $lines[] = "\n\033[1m{$this->total} sins\033[0m across {$skills} " . ($skills === 1 ? 'skill' : 'skills') . '.';
        $lines[] = "\033[2m↳ the rule above all: trace each sin to where the value is BORN and fix it THERE — read fix-at-the-source.\033[0m";
        $lines[] = "\033[2m↳ don't recognise a rule above? `vendor/bin/commandments info <sin>` — what it flags, why it is a sin, the fix, an example.\033[0m";

        return implode("\n", $lines);
    }

    /**
     * The Markdown task list: LOAD the section's skill, fix the sin at `file:line`,
     * delete the line. When it's empty, a clean re-run deletes the file.
     */
    public function checklist(): string
    {
        $out = "# Code Commandments — {$this->total} sins to fix\n\n"
            . "> 🔱 **The rule above all — `fix-at-the-source`.** Every sin below is a SYMPTOM. "
            . "Before you change a line, trace the value to where it is BORN and fix it there; "
            . "the symptom (and often others) then disappears on its own. Never silence it with a "
            . "`?? default`, a cast, or a null-check.\n\n"
            . "**This file is your worklist. Work it straight down, deleting as you go — do NOT "
            . "stop to re-check.** For each line, top to bottom, do exactly this:\n\n"
            . "1. **LOAD the skill named in the section header.** It "
            . "teaches the fix; do NOT fix from memory. Even if you believe you already loaded it, treat "
            . "it as NOT loaded (a context compaction may have silently dropped its instructions while "
            . "leaving you the impression they're still there) and load it again before touching the "
            . "section. Once per section is enough.\n"
            . "   _Don't recognise the rule, or about to argue with it? Run "
            . "`vendor/bin/commandments info <sin>` first — it prints what the rule flags, WHY it is a "
            . "sin, how it is fixed, and a worked example. The detector name from the line works as the "
            . "argument. Guessing at a rule you have not read is how a finding gets silenced instead of "
            . "fixed._\n"
            . "2. Open the `file:line` and fix the sin at its source.\n"
            . "3. **Delete that line from this file.** Nothing else — no tick, no mark, no "
            . "strike-through. The deleted line IS the record that it's fixed.\n\n"
            . "**Do NOT re-run `judge`, re-scan, or re-verify between fixes.** That is slow and "
            . "pointless: the shrinking file is your only source of truth, and each deleted line "
            . "is its own confirmation. Do not pause to check your work — just fix, delete, and "
            . "move to the next line until none remain.\n\n"
            . "Work **wave by wave.** ONLY when this list is EMPTY, run `commandments judge` again. "
            . "If your fixes rippled into other files, it writes a fresh worklist — a new wave; work "
            . "it exactly the same way (fix, delete, no re-checks between). Repeat, judging only "
            . "between waves, until a run is clean and deletes this file.\n";

        foreach ($this->bySkill as $skill => $findings) {
            $out .= "\n## {$skill}\n\n"
                . "> ▶ **" . Skill::loadInstruction($skill) . ".** "
                . "It teaches every fix below. Don't work from memory, and don't assume it's still loaded "
                . "from earlier — a compaction can drop it silently — load it again if in any doubt.\n\n";

            foreach ($this->fixCommands($findings) as $command) {
                $out .= "> ✎ Auto-fixable — run `{$command}` to repent these for you.\n\n";
            }

            foreach ($this->scaffoldCommands($findings) as $command) {
                $out .= "> 🛠 Scaffold the helper its fix uses — run `{$command}`.\n\n";
            }

            if ($this->hasCustom($findings)) {
                $out .= '> 🧾 ' . self::CUSTOM_NOTE . "\n\n";
            }

            foreach ($findings as $finding) {
                // The FULL path (not display-relative) so `--repent=ID` can resolve it.
                $out .= "- `{$finding->location}`  {$finding->scope}  [{$finding->rule()}]"
                    . ($finding->twins === [] ? '' : ' — same shape as ' . $this->twins($finding))
                    . "\n";
            }
        }

        return $out;
    }

    /**
     * The sibling occurrences a RECURRENCE verdict rests on, rendered display-relative. A recurrence sin
     * is unreadable without them — the site alone looks innocent, and the reader has no way to see what
     * it was bucketed WITH. Capped, because a shape repeated forty times needs a name, not forty paths.
     */
    private function twins(Finding $finding): string
    {
        $shown = array_map($this->relative(...), array_slice($finding->twins, 0, self::TWINS_SHOWN));
        $rest = count($finding->twins) - count($shown);

        return implode(', ', $shown) . ($rest > 0 ? " (+{$rest} more)" : '');
    }

    /**
     * Does any of a group's findings come from a rule the project owns?
     *
     * @param  list<Finding>  $findings
     */
    private function hasCustom(array $findings): bool
    {
        foreach ($findings as $finding) {
            if ($finding->custom) {
                return true;
            }
        }

        return false;
    }

    /**
     * The distinct `repent` commands for the auto-fixable sins among a group's findings.
     *
     * @param  list<Finding>  $findings
     * @return list<string>
     */
    private function fixCommands(array $findings): array
    {
        $commands = [];

        foreach ($findings as $finding) {
            if (isset($this->fixable[$finding->sin])) {
                $commands[$finding->sin] = $this->fixable[$finding->sin];
            }
        }

        return array_values($commands);
    }

    /**
     * The distinct `scaffold` commands for sins (in a group's findings) whose fix uses a
     * generic helper the package can generate.
     *
     * @param  list<Finding>  $findings
     * @return list<string>
     */
    private function scaffoldCommands(array $findings): array
    {
        $commands = [];

        foreach ($findings as $finding) {
            if (isset($this->scaffoldable[$finding->sin])) {
                $commands[$finding->sin] = $this->scaffoldable[$finding->sin];
            }
        }

        return array_values($commands);
    }

    private function relative(string $location): string
    {
        return str_starts_with($location, $this->path . '/') ? substr($location, strlen($this->path) + 1) : $location;
    }
}
