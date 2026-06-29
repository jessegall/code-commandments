<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Cli\Scope\BranchChanges;
use JesseGall\CodeCommandments\Cli\Scope\ChangeScope;
use JesseGall\CodeCommandments\Cli\Scope\EntireCodebase;
use JesseGall\CodeCommandments\Cli\Scope\ScopeUnavailable;
use JesseGall\CodeCommandments\Cli\Scope\WorkingTreeChanges;
use JesseGall\CodeCommandments\Detectors\Catalog;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * `commandments judge [path] [--skill=NAME] [--detector=NAME] [--changes] [--branch[=BASE]] [--parallel=N] [--list]`
 *
 * Scans a path, runs the Sin Detectors, and prints each finding as
 * `file:line  Class::method`, grouped by the SKILL that teaches the fix — so an
 * agent can read one skill and resolve the whole group. Filter to a skill (group)
 * or a single detector to scope a fixing pass.
 *
 * It orchestrates four collaborators: a {@see ChangeScope} (chosen by a `match` on
 * the flags) decides which files to report on; {@see Codebase} parses the path; the
 * {@see DetectorRunner} runs the detectors (in parallel) into lightweight findings;
 * a {@see SinReport} renders the console output and the checklist.
 *
 * By default it also writes a Markdown checklist (`commandments-sins.md`) — the
 * intended workflow is to judge ONCE, then work that file line-by-line (a full
 * scan is slow), deleting each line as its sin is fixed. `--no-checklist` prints
 * only; `--checklist=FILE` retargets it.
 *
 * @phpstan-import-type Finding from DetectorRunner
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

        try {
            $changed = $this->scope($options)->restrictTo($options['path']);
        } catch (ScopeUnavailable $unavailable) {
            fwrite(STDERR, $unavailable->getMessage() . "\n");

            return 2;
        }

        return $this->judge($options['path'], $detectors, $options['exclude'], $options['checklist'], $changed, $options['parallel']);
    }

    /**
     * Pick the file-scoping strategy from the flags. Most flags aren't strategies;
     * only the change-scope axis (whole / working tree / branch) is.
     *
     * @param  array{git: bool, branch: ?string, ...}  $options
     */
    private function scope(array $options): ChangeScope
    {
        return match (true) {
            $options['branch'] !== null => new BranchChanges($options['branch']),
            $options['git'] => new WorkingTreeChanges,
            default => new EntireCodebase,
        };
    }

    /**
     * @param  list<Detector>  $detectors
     * @param  list<string>  $exclude
     * @param  array<string, true>|null  $changed  Restrict findings to these files (absolute paths); null = no filter.
     */
    private function judge(string $path, array $detectors, array $exclude, ?string $checklist, ?array $changed, int $parallel): int
    {
        if ($changed === []) {
            $this->deleteChecklist($checklist);
            $this->line("\033[32m✓ No changed files to judge.\033[0m");

            return 0;
        }

        $progress = new ProgressBar;
        $progress->status("parsing {$path} …");

        $codebase = Codebase::scan($path);

        $findings = new DetectorRunner($parallel)->run($detectors, $codebase, $progress);

        $progress->finish();

        $findings = $this->keep($findings, $exclude, $changed);

        if ($findings === []) {
            $this->deleteChecklist($checklist);
            $this->line("\033[32m✓ No sins found.\033[0m");

            return 0;
        }

        $report = new SinReport($path, $findings);
        $this->line($report->console());

        if ($checklist !== null) {
            file_put_contents($checklist, $report->checklist());
            $this->line("\033[2m↳ checklist written to {$checklist} — fix each item, then delete its line\033[0m");
        }

        return 1;
    }

    /**
     * Drop findings in generated/excluded files and, when a scope is active, those
     * outside the changed set.
     *
     * @param  list<Finding>  $findings
     * @param  list<string>  $exclude
     * @param  array<string, true>|null  $changed
     * @return list<Finding>
     */
    private function keep(array $findings, array $exclude, ?array $changed): array
    {
        $kept = [];

        foreach ($findings as $finding) {
            if ($this->isExcluded($finding['file'], $exclude)) {
                continue;
            }

            if ($changed !== null && ! $this->isChanged($finding['file'], $changed)) {
                continue;
            }

            $kept[] = $finding;
        }

        return $kept;
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
     * @return array{path: string, skill: ?string, detector: ?string, list: bool, exclude: list<string>, checklist: ?string, git: bool, branch: ?string, parallel: int}
     */
    private function parse(array $args): array
    {
        $path = '.';
        $skill = null;
        $detector = null;
        $list = false;
        $git = false;
        $branch = null;
        $parallel = 8;
        $exclude = [];

        // By default the findings are written to a checklist file the agent prunes
        // line-by-line; `--no-checklist` prints only, `--checklist=FILE` retargets.
        $checklist = 'commandments-sins.md';

        foreach ($args as $arg) {
            if ($arg === '--list') {
                $list = true;
            } elseif ($arg === '--changes' || $arg === '--git') {
                // `--git` is the original spelling, kept as a quiet alias for `--changes`.
                $git = true;
            } elseif ($arg === '--branch') {
                $branch = 'main';
            } elseif (str_starts_with($arg, '--branch=')) {
                $branch = substr($arg, 9);
            } elseif (str_starts_with($arg, '--parallel=')) {
                $parallel = max(1, (int) substr($arg, 11));
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

        return ['path' => rtrim($path, '/'), 'skill' => $skill, 'detector' => $detector, 'list' => $list, 'exclude' => $exclude, 'checklist' => $checklist, 'git' => $git, 'branch' => $branch, 'parallel' => $parallel];
    }

    /**
     * Is a finding's file one of the changed files? Compared by absolute path, since
     * scanned paths are relative to the judged directory.
     *
     * @param  array<string, true>  $changed
     */
    private function isChanged(string $file, array $changed): bool
    {
        $absolute = realpath($file);

        return $absolute !== false && isset($changed[$absolute]);
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

    private function deleteChecklist(?string $checklist): void
    {
        if ($checklist !== null && is_file($checklist)) {
            @unlink($checklist);
        }
    }

    private function shortName(Detector $detector): string
    {
        $parts = explode('\\', $detector::class);

        return end($parts);
    }

    private function line(string $text): void
    {
        fwrite(STDOUT, $text . "\n");
    }
}
