<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Cli\Scope\Scope;
use JesseGall\CodeCommandments\Cli\Scope\ScopeUnavailable;
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
 * It orchestrates four collaborators: a {@see Scope} (resolved from the flags)
 * decides which files to report on; {@see Codebase} parses the path; the
 * {@see DetectorRunner} runs the detectors (in parallel) into lightweight findings;
 * a {@see SinReport} renders the console output and the checklist.
 *
 * By default it also writes a Markdown checklist (`.commandments/sins.md`, inside
 * the gitignored `.commandments/` artifact folder) — the intended workflow is to
 * judge ONCE, then work that file line-by-line (a full scan is slow), deleting each
 * line as its sin is fixed. `--no-checklist` prints only; `--checklist=FILE` retargets it.
 */
final class Judge
{
    /** @var array<string, bool> */
    private array $generated = [];

    public function run(array $args): int
    {
        $options = JudgeOptions::fromArgs($args);

        if ($options->list) {
            return $this->list();
        }

        if (! is_dir($options->path)) {
            fwrite(STDERR, "Not a directory: {$options->path}\n");

            return 2;
        }

        $detectors = $this->select($options->skill, $options->detector);

        if ($detectors === []) {
            fwrite(STDERR, "No detector matched --skill={$options->skill} --detector={$options->detector}\n");

            return 2;
        }

        try {
            $scope = Scope::fromArgs($args, $options->path);
        } catch (ScopeUnavailable $unavailable) {
            fwrite(STDERR, $unavailable->getMessage() . "\n");

            return 2;
        }

        return $this->judge($options->path, $options->pathGiven, $detectors, $options->exclude, $options->checklist, $scope, $options->parallel, $options->benchmark);
    }

    /**
     * @param  list<Detector>  $detectors
     * @param  list<string>  $exclude
     */
    private function judge(string $path, bool $pathGiven, array $detectors, array $exclude, ?string $checklist, Scope $scope, int $parallel, bool $benchmark): int
    {
        if ($scope->isEmpty()) {
            $this->deleteChecklist($checklist);
            $this->line("\033[32m✓ No changed files to judge.\033[0m");

            return 0;
        }

        // An explicit path is scanned as given; otherwise the canon decides which
        // source roots to judge (hydrated from the project on first run).
        $roots = $path;

        if (! $pathGiven) {
            $canon = new Canon()->resolve($path);
            $roots = $canon->paths;

            if ($canon->hydrated) {
                $this->line("\033[2m↳ wrote {$canon->file} — the backend source roots judged; edit it to adjust scope\033[0m");
            }
        }

        $progress = new ProgressBar;

        $parseStart = hrtime(true);
        $codebase = Codebase::scan($roots, static function (int $done, int $total) use ($progress): void {
            $progress->track($done, $total, 'parsing');
        });
        $parseSeconds = (hrtime(true) - $parseStart) / 1e9;

        if ($benchmark) {
            $bench = new Benchmark;
            $findings = $bench->run($detectors, $codebase);
            $progress->finish();
            fwrite(STDERR, $bench->render($parseSeconds));
        } else {
            $findings = new DetectorRunner($parallel)->run($detectors, $codebase, $progress);
            $progress->finish();
        }

        $findings = $this->keep($findings, $exclude, $scope);

        if ($findings === []) {
            $this->deleteChecklist($checklist);
            $this->line("\033[32m✓ No sins found.\033[0m");

            return 0;
        }

        $report = new SinReport($path, $findings);
        $this->line($report->console());

        if ($checklist !== null) {
            @mkdir(dirname($checklist), 0755, true);
            $this->archive($checklist);
            file_put_contents($checklist, $report->checklist());
            $this->line("\033[2m↳ checklist written to {$checklist} — fix each item, then delete its line\033[0m");
        }

        return 1;
    }

    /**
     * Before overwriting the checklist, preserve the previous one alongside it as
     * `<name>-<when>.<ext>` (stamped with its own write time) — so a re-run never
     * clobbers the report you were working through. Archives live in the gitignored
     * `.commandments/` folder; clear them out whenever.
     */
    private function archive(string $checklist): void
    {
        if (! is_file($checklist)) {
            return;
        }

        $ext = pathinfo($checklist, PATHINFO_EXTENSION);
        $stem = $ext === '' ? $checklist : substr($checklist, 0, -(strlen($ext) + 1));
        $stamp = date('Y-m-d_His', @filemtime($checklist) ?: time());

        $archive = "{$stem}-{$stamp}" . ($ext === '' ? '' : ".{$ext}");

        // A second run within the same second would collide — keep both.
        for ($n = 2; is_file($archive); $n++) {
            $archive = "{$stem}-{$stamp}-{$n}" . ($ext === '' ? '' : ".{$ext}");
        }

        @rename($checklist, $archive);
    }

    /**
     * Drop findings in generated/excluded files (always), and those out of scope.
     * Exclusion and scope are separate concerns: exclude is the `--exclude` fragments
     * plus the `@code-commandments-generated` marker; scope is the changed-file set.
     *
     * @param  list<Finding>  $findings
     * @param  list<string>  $exclude
     * @return list<Finding>
     */
    private function keep(array $findings, array $exclude, Scope $scope): array
    {
        $kept = [];

        foreach ($findings as $finding) {
            if ($this->isExcluded($finding->file, $exclude)) {
                continue;
            }

            if (! $scope->includes($finding->file)) {
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
