<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * Renders the kept findings two ways: the coloured console report (grouped by the
 * skill that fixes each sin) and the Markdown checklist the agent prunes line by
 * line. Findings are grouped by skill and ordered by `file:line`, so the output is
 * identical no matter how the detector workers interleaved.
 */
final class SinReport
{
    /** @var array<string, list<Finding>> */
    private array $bySkill;

    private int $total;

    /**
     * @param  list<Finding>  $findings
     * @param  array<string, string>  $fixable  sin name => the `repent` command that fixes it (scope included)
     */
    public function __construct(private readonly string $path, array $findings, private readonly array $fixable = [])
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
            $lines[] = "  \033[2m↳ read the {$skill} skill (skills/commandments/{$skill}/SKILL.md) before fixing\033[0m";

            foreach ($findings as $finding) {
                $location = $this->relative($finding->location);
                $lines[] = "  \033[36m{$location}\033[0m  {$finding->scope}  \033[2m[{$finding->detector}]\033[0m";
            }

            foreach ($this->fixCommands($findings) as $command) {
                $lines[] = "  \033[32m↳ auto-fixable: {$command}\033[0m";
            }
        }

        $skills = count($this->bySkill);
        $lines[] = "\n\033[1m{$this->total} sins\033[0m across {$skills} " . ($skills === 1 ? 'skill' : 'skills') . '.';
        $lines[] = "\033[2m↳ the rule above all: trace each sin to where the value is BORN and fix it THERE — read fix-at-the-source.\033[0m";

        return implode("\n", $lines);
    }

    /**
     * The Markdown task list: read the skill, fix the sin at `file:line`, delete the
     * line. When it's empty, a clean re-run deletes the file.
     */
    public function checklist(): string
    {
        $out = "# Code Commandments — {$this->total} sins to fix\n\n"
            . "> 🔱 **The rule above all — `fix-at-the-source`.** Every sin below is a SYMPTOM. "
            . "Before you change a line, trace the value to where it is BORN and fix it there; "
            . "the symptom (and often others) then disappears on its own. Never silence it with a "
            . "`?? default`, a cast, or a null-check.\n\n"
            . "**This file is your worklist, and DELETION is how you work it.** Each line "
            . "below is one unfixed sin. Go top to bottom, ONE at a time:\n\n"
            . "1. Read the skill named in the section header (it teaches the fix).\n"
            . "2. Open the `file:line` and fix the sin at the source.\n"
            . "3. **Delete its line from this file** — do NOT tick it, mark it, or strike it "
            . "through. A fixed sin leaves no trace; the line is simply gone.\n\n"
            . "The shrinking file IS your progress — every line you delete is a sin resolved. "
            . "When no lines remain, you're done: re-run `commandments judge` to confirm (a "
            . "clean run deletes this file).\n";

        foreach ($this->bySkill as $skill => $findings) {
            $out .= "\n## {$skill}  — read `skills/commandments/{$skill}/SKILL.md`\n\n";

            foreach ($this->fixCommands($findings) as $command) {
                $out .= "> ✎ Auto-fixable — run `{$command}` to repent these for you.\n\n";
            }

            foreach ($findings as $finding) {
                // The FULL path (not display-relative) so `--repent=ID` can resolve it.
                $out .= "- `{$finding->location}`  {$finding->scope}  [{$finding->detector}]\n";
            }
        }

        return $out;
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

    private function relative(string $location): string
    {
        return str_starts_with($location, $this->path . '/') ? substr($location, strlen($this->path) + 1) : $location;
    }
}
