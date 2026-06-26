<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Detectors\Catalog;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * `commandments judge [path] [--skill=NAME] [--detector=NAME] [--list]`
 *
 * Scans a path, runs the Sin Detectors, and prints each finding as
 * `file:line  Class::method`, grouped by the SKILL that teaches the fix — so an
 * agent can read one skill and resolve the whole group. Filter to a skill (group)
 * or a single detector to scope a fixing pass.
 *
 * By default it also writes a Markdown checklist (`commandments-sins.md`) — the
 * intended workflow is to judge ONCE, then work that file line-by-line (a full
 * scan is slow), deleting each line as its sin is fixed. `--no-checklist` prints
 * only; `--checklist=FILE` retargets it.
 */
final class Judge
{
    /** @var array<string, bool> */
    private array $generated = [];

    public function run(array $args): int
    {
        $options = $this->parse($args);

        if ($options['list']) {
            return $this->list();
        }

        if (! is_dir($options['path'])) {
            fwrite(STDERR, "Not a directory: {$options['path']}\n");

            return 2;
        }

        $detectors = $this->select($options['skill'], $options['detector']);

        if ($detectors === []) {
            fwrite(STDERR, "No detector matched --skill={$options['skill']} --detector={$options['detector']}\n");

            return 2;
        }

        return $this->judge($options['path'], $detectors, $options['exclude'], $options['checklist']);
    }

    /**
     * @param  list<Detector>  $detectors
     * @param  list<string>  $exclude
     */
    private function judge(string $path, array $detectors, array $exclude, ?string $checklist): int
    {
        $codebase = Codebase::scan($path);

        /** @var array<string, list<array{detector: string, match: NodeMatch}>> $bySkill */
        $bySkill = [];

        foreach ($detectors as $detector) {
            foreach ($detector->find($codebase) as $match) {
                if ($this->isExcluded($match->file->path, $exclude)) {
                    continue;
                }

                $bySkill[$detector->skill()][] = ['detector' => $this->shortName($detector), 'match' => $match];
            }
        }

        if ($bySkill === []) {
            if ($checklist !== null && is_file($checklist)) {
                @unlink($checklist);
            }

            $this->line("\033[32m✓ No sins found.\033[0m");

            return 0;
        }

        ksort($bySkill);
        $total = 0;

        foreach ($bySkill as $skill => $findings) {
            $total += count($findings);
            $this->line("\n\033[1;33m{$skill}\033[0m  (" . count($findings) . ")");
            $this->line("  \033[2m↳ read the {$skill} skill (skills/{$skill}/SKILL.md) before fixing\033[0m");

            foreach ($findings as $finding) {
                $location = $this->relative($path, $finding['match']->location());
                $this->line("  \033[36m{$location}\033[0m  {$finding['match']->scope()}  \033[2m[{$finding['detector']}]\033[0m");
            }
        }

        $skills = count($bySkill);
        $this->line("\n\033[1m{$total} sins\033[0m across {$skills} " . ($skills === 1 ? 'skill' : 'skills') . ".");

        if ($checklist !== null) {
            file_put_contents($checklist, $this->checklist($path, $bySkill, $total));
            $this->line("\033[2m↳ checklist written to {$checklist} — fix each item, then delete its line\033[0m");
        }

        return 1;
    }

    /**
     * Render the findings as a Markdown task list the agent works through and
     * prunes line-by-line: read the skill, fix the sin, delete the line.
     *
     * @param  array<string, list<array{detector: string, match: NodeMatch}>>  $bySkill
     */
    private function checklist(string $path, array $bySkill, int $total): string
    {
        $out = "# Code Commandments — {$total} sins to fix\n\n"
            . "A checklist. Work through it ONE sin at a time, top to bottom:\n\n"
            . "1. Read the skill named in the section header (it teaches the fix).\n"
            . "2. Open the `file:line` and fix the sin at the source.\n"
            . "3. **Delete that line from this file.**\n\n"
            . "When the list is empty, run `commandments judge` again to confirm "
            . "(a clean run deletes this file).\n";

        foreach ($bySkill as $skill => $findings) {
            $out .= "\n## {$skill}  — read `skills/{$skill}/SKILL.md`\n\n";

            foreach ($findings as $finding) {
                $location = $this->relative($path, $finding['match']->location());
                $out .= "- [ ] `{$location}`  {$finding['match']->scope()}  [{$finding['detector']}]\n";
            }
        }

        return $out;
    }

    private function list(): int
    {
        /** @var array<string, list<string>> $bySkill */
        $bySkill = [];

        foreach (Catalog::all() as $detector) {
            $bySkill[$detector->skill()][] = $this->shortName($detector);
        }

        ksort($bySkill);

        foreach ($bySkill as $skill => $detectors) {
            $this->line("\033[1;33m{$skill}\033[0m");

            foreach ($detectors as $detector) {
                $this->line("  {$detector}");
            }
        }

        return 0;
    }

    /**
     * @return list<Detector>
     */
    private function select(?string $skill, ?string $detector): array
    {
        return array_values(array_filter(Catalog::all(), function (Detector $candidate) use ($skill, $detector): bool {
            if ($skill !== null && $candidate->skill() !== $skill) {
                return false;
            }

            return $detector === null || stripos($this->shortName($candidate), $detector) !== false;
        }));
    }

    /**
     * @return array{path: string, skill: ?string, detector: ?string, list: bool, exclude: list<string>, checklist: ?string}
     */
    private function parse(array $args): array
    {
        $path = '.';
        $skill = null;
        $detector = null;
        $list = false;
        $exclude = [];

        // By default the findings are written to a checklist file the agent prunes
        // line-by-line; `--no-checklist` prints only, `--checklist=FILE` retargets.
        $checklist = 'commandments-sins.md';

        foreach ($args as $arg) {
            if ($arg === '--list') {
                $list = true;
            } elseif ($arg === '--no-checklist') {
                $checklist = null;
            } elseif (str_starts_with($arg, '--checklist=')) {
                $checklist = substr($arg, 12);
            } elseif (str_starts_with($arg, '--skill=')) {
                $skill = substr($arg, 8);
            } elseif (str_starts_with($arg, '--detector=')) {
                $detector = substr($arg, 11);
            } elseif (str_starts_with($arg, '--exclude=')) {
                $exclude = array_values(array_filter(explode(',', substr($arg, 10))));
            } elseif (! str_starts_with($arg, '--')) {
                $path = $arg;
            }
        }

        return ['path' => rtrim($path, '/'), 'skill' => $skill, 'detector' => $detector, 'list' => $list, 'exclude' => $exclude, 'checklist' => $checklist];
    }

    /**
     * Generated code (`@code-commandments-generated`) is regenerated, not hand-
     * authored, so fixing a finding there is futile — it's skipped. So is any path
     * matching a `--exclude` fragment.
     *
     * @param  list<string>  $exclude
     */
    private function isExcluded(string $path, array $exclude): bool
    {
        foreach ($exclude as $fragment) {
            if ($fragment !== '' && str_contains($path, $fragment)) {
                return true;
            }
        }

        return $this->generated[$path] ??= str_contains((string) @file_get_contents($path), '@code-commandments-generated');
    }

    private function shortName(Detector $detector): string
    {
        $parts = explode('\\', $detector::class);

        return end($parts);
    }

    private function relative(string $path, string $location): string
    {
        return str_starts_with($location, $path . '/') ? substr($location, strlen($path) + 1) : $location;
    }

    private function line(string $text): void
    {
        fwrite(STDOUT, $text . "\n");
    }
}
