<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Cli\Scope\Scope;
use JesseGall\CodeCommandments\Cli\Scope\ScopeUnavailable;
use JesseGall\CodeCommandments\Detector as RootDetector;
use JesseGall\CodeCommandments\Detectors\Catalog;
use JesseGall\CodeCommandments\Detectors\Detector;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Vue\Codebase as VueCodebase;

/**
 * `commandments judge [path] [--skill=NAME] [--sin=NAME] [--changes] [--branch[=BASE]] [--parallel=N] [--list]`
 *
 * Scans a path, runs the Sin Detectors, and prints each finding as
 * `file:line  Class::method`, grouped by the SKILL that teaches the fix — so an
 * agent can read one skill and resolve the whole group. Filter to a skill (group)
 * or a single sin (`--sin=array-bag`) to scope a fixing pass.
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

        $detectors = $this->select(Catalog::backend(), $options->skill, $options->sin);
        $frontend = $this->select(Catalog::frontend(), $options->skill, $options->sin);

        if ($detectors === [] && $frontend === []) {
            fwrite(STDERR, "No detector matched --skill={$options->skill} --sin={$options->sin}\n");

            return 2;
        }

        try {
            $scope = Scope::fromArgs($args, $options->path);
        } catch (ScopeUnavailable $unavailable) {
            fwrite(STDERR, $unavailable->getMessage() . "\n");

            return 2;
        }

        return $this->judge($options->path, $options->pathGiven, $detectors, $frontend, $options->exclude, $options->checklist, $scope, $options->parallel, $options->benchmark, $this->fixCommands($args, $options));
    }

    /**
     * The `repent` command (scope and all) for each auto-fixable sin — a {@see Repentable}
     * detector's, so the report can advertise the one-liner that fixes it. The scope
     * mirrors this judge run: the same path and the same `--changes`/`--branch` flag.
     *
     * @return array<string, string>  sin name => repent command
     */
    private function fixCommands(array $args, JudgeOptions $options): array
    {
        $scope = $options->pathGiven ? [$options->path] : [];

        foreach ($args as $arg) {
            if (in_array($arg, ['--changes', '--git', '--branch'], true) || str_starts_with($arg, '--branch=')) {
                $scope[] = $arg;
            }
        }

        $prefix = $scope === [] ? '' : implode(' ', $scope) . ' ';
        $commands = [];

        foreach (Catalog::frontend() as $detector) {
            if ($detector instanceof Repentable) {
                $name = $detector->sin()->name();
                $commands[$name] = "vendor/bin/commandments repent {$prefix}--sin={$name}";
            }
        }

        return $commands;
    }

    /**
     * @param  list<Detector>  $detectors  backend (PHP) detectors
     * @param  list<\JesseGall\CodeCommandments\Vue\Detector>  $frontend  Vue detectors
     * @param  list<string>  $exclude
     */
    private function judge(string $path, bool $pathGiven, array $detectors, array $frontend, array $exclude, ?string $checklist, Scope $scope, int $parallel, bool $benchmark, array $fixable): int
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

        // The Vue detectors run over the SAME roots — `judge` is engine-agnostic, so a
        // path with `.vue` files reports its frontend sins alongside the backend ones.
        $findings = array_merge($findings, $this->frontendFindings($roots, $frontend));

        $findings = $this->keep($findings, $exclude, $scope);

        if ($findings === []) {
            $this->deleteChecklist($checklist);
            $this->line("\033[32m✓ No sins found.\033[0m");

            return 0;
        }

        $report = new SinReport($path, $findings, $fixable);
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
            $parts = explode('\\', $detector::class);
            $bySkill[$detector->sin()->slug()][] = end($parts);
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
     * The Vue detectors' findings over the roots — scanned once, reduced to the same
     * lightweight {@see Finding}s the backend produces (a Vue {@see ElementMatch} already
     * knows its `file:line` and scope).
     *
     * @param  string|list<string>  $roots
     * @param  list<\JesseGall\CodeCommandments\Vue\Detector>  $frontend
     * @return list<Finding>
     */
    private function frontendFindings(string|array $roots, array $frontend): array
    {
        if ($frontend === []) {
            return [];
        }

        $codebase = VueCodebase::scan($roots);
        $findings = [];

        foreach ($frontend as $detector) {
            $sin = $detector->sin();
            $parts = explode('\\', $detector::class);
            $short = end($parts);

            foreach ($detector->find($codebase) as $match) {
                $findings[] = new Finding($short, $sin->slug(), $sin->name(), $match->file(), $match->location(), $match->scope());
            }
        }

        return $findings;
    }

    /**
     * @param  list<Detector>  $detectors
     * @return list<Detector>
     */
    private function select(array $detectors, ?string $skill, ?string $sin): array
    {
        return array_values(array_filter($detectors, static function (RootDetector $candidate) use ($skill, $sin): bool {
            if ($skill !== null && $candidate->sin()->slug() !== $skill) {
                return false;
            }

            return $sin === null || $candidate->sin()->matches($sin);
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
