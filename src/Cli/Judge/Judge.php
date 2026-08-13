<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Judge;

use JesseGall\CodeCommandments\Finding;

use JesseGall\CodeCommandments\Support\ClassName;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Bridge\Bridge;
use JesseGall\CodeCommandments\Bridge\ConsumesContracts;
use JesseGall\CodeCommandments\Cli\Config\SourceRoots;
use JesseGall\CodeCommandments\Cli\Scope\Scope;
use JesseGall\CodeCommandments\Cli\Scope\ScopeUnavailable;
use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Languages;
use JesseGall\CodeCommandments\Custom;
use JesseGall\CodeCommandments\ExcludedPaths;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Packages\Exemptions;
use JesseGall\CodeCommandments\Detector as RootDetector;
use JesseGall\CodeCommandments\Detectors\Catalog;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Sins\Catalog as Sins;
use JesseGall\CodeCommandments\Vue\Codebase as VueCodebase;

use JesseGall\CodeCommandments\Cli\Report\SinReport;
use JesseGall\CodeCommandments\Cli\Report\SkippedRules;
use JesseGall\CodeCommandments\Cli\Attempt;
use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Benchmark;
use JesseGall\CodeCommandments\Cli\ProgressBar;
use JesseGall\CodeCommandments\Hooks\HookIO;
use JesseGall\CodeCommandments\Workspace;
use JesseGall\PhpTypes\Option;

/**
 * Scans a path and runs Sin Detectors, outputting findings grouped by skill (filterable by --skill/--sin).
 * Orchestrates Scope, Codebase, DetectorRunner (parallel), and SinReport; writes `.commandments/sins.md` checklist.
 */
final class Judge implements Command
{
    /**
     * How many past checklists to keep alongside the live one.
     */
    private const int KEEP_ARCHIVES = 5;

    /**
     * The exit code for a run that found nothing but could not run every rule — distinct from
     * both "clean" (0) and "sins found" (1), because a broken rule is neither.
     */
    private const int A_RULE_COULD_NOT_RUN = 3;

    public function __construct(private readonly HookIO $io = new HookIO) {}

    public function names(): array
    {
        return ['judge'];
    }

    public function help(): Help
    {
        return Help::of('Scan a codebase and report its sins, grouped by the skill that fixes each. Exit code 1 when sins are found, 3 when a rule could not run.')
            ->form('judge [path]', 'scan a path — or, with none, the source roots declared in .commandments/config.php')
            ->form('judge --list', 'list every detector, grouped by skill')
            ->option('--skill=NAME', 'only run detectors for one skill (group), e.g. spatie-data')
            ->option('--sin=NAME', 'only run detectors for one sin (lenient name match), e.g. nullable-callback')
            ->option('--exclude=A,B', 'skip findings in paths containing any fragment')
            ->adopt(Scope::options())
            ->option('--parallel=N', 'run detectors across N worker processes (default: 8, capped at cores; 1 = off)')
            ->option('--ignore-package-requirements', 'keep package-gated rules even if this project lacks the package (cross-project calibration)')
            ->option('--checklist=FILE', "write the checklist here (default: your session's .commandments/sessions/<id>/sins.md)")
            ->option('--no-checklist', "print only, don't write the checklist file")
            ->option('--benchmark', 'time each detector and print the slowest')
            ->note('With no [path], judge scans the source roots declared by $config->paths(...) in '
                . '.commandments/config.php — auto-detected on first run from your composer.json PSR-4 map (plus '
                . 'app/src), so scaffolding like database/, storage/ and config/ is not judged. Run `commandments config '
                . 'reindex` to re-detect them, or pass an explicit [path] to scan it directly. Add '
                . "\$config->exclude('app/Generated') to subtract a path from ANY run — the tree is still parsed (so "
                . 'cross-file rules stay correct) but nothing in it is ever reported or rewritten.')
            ->note('A rule that BREAKS is skipped so the rest of the run survives — but the run is not green: it '
                . 'names the rules that could not run and exits 3 (rather than 0) when nothing else was found, '
                . 'because a run missing a rule has not judged what that rule judges.')
            ->note('Judge writes a Markdown checklist into your session folder (the run prints the exact path). '
                . 'A full scan is slow, so judge ONCE and work that file line-by-line, deleting each line as you fix its sin; re-run judge at the end to confirm. Files marked @code-commandments-generated are skipped — they are regenerated, not hand-authored.');
    }

    public function run(Input $input): int
    {
        // The same root resolution as the hooks (git toplevel → CLAUDE_PROJECT_DIR → cwd), so a judge
        // run from a subdirectory still lands its artifacts in the SAME session folder the hooks read.
        $workspace = Workspace::at($this->io->projectRoot());
        $options = JudgeOptions::fromInput($input, $workspace);

        if ($options->list) {
            return $this->list();
        }

        if (! is_dir($options->path)) {
            fwrite(STDERR, "Not a directory: {$options->path}\n");

            return 2;
        }

        // Apply the project's `.commandments/config.php` (disable / detector / package / configure)
        // to the shipped catalogs before the CLI `--skill`/`--sin` narrowing.
        $config = Config::load();

        // `--ignore-package-requirements` lets one checkout judge ANY project's tree — a
        // package-gated rule (Spatie/Laravel/…) is kept even though THIS project doesn't
        // require the package. For cross-project calibration; user `disable()`s still apply.
        $installed = $options->ignorePackages ? static fn () => true : null;
        $configured = $config->apply(Catalog::backend(), Catalog::frontend(), $installed);
        $detectors = $this->select($configured['backend'], $options->skill, $options->sin);
        $frontend = $this->select($configured['frontend'], $options->skill, $options->sin);

        if ($detectors === [] && $frontend === []) {
            $named = "--skill={$options->skill->unwrapOr('')} --sin={$options->sin->unwrapOr('')}";
            fwrite(STDERR, "No detector matched {$named}\n");

            return 2;
        }

        try {
            $scope = Scope::fromArgs($input->raw(), $options->path, $workspace);
        } catch (ScopeUnavailable $unavailable) {
            fwrite(STDERR, $unavailable->getMessage() . "\n");

            return 2;
        }

        // Scoping to a checklist (`--repent=ID|latest`) must NOT write a new one — it
        // would clobber the very file it's reading. Force `--no-checklist` there.
        $checklist = Scope::repent($input->raw()) !== null ? Option::none() : $options->checklist;

        return $this->judge($options->path, $options->pathGiven, $detectors, $frontend, $options->exclude, $checklist, $scope, $options->parallel, $options->benchmark, $this->fixCommands(), $this->scaffoldCommands());
    }

    /**
     * The `scaffold` command for each sin whose fix uses a generic, package-providable
     * helper ({@see Sin::scaffolds}), so the report can advertise the one-liner that
     * generates it.
     *
     * @return array<string, string>  sin name => scaffold command
     */
    private function scaffoldCommands(): array
    {
        $commands = [];

        foreach (Sins::every() as $sin) {
            if ($sin->scaffolds() === []) {
                continue;
            }

            $name = $sin->name();
            $commands[$name] = "vendor/bin/commandments scaffold --sin={$name}";
        }

        return $commands;
    }

    /**
     * The `repent` command for each auto-fixable sin — a {@see Repentable} detector's, so
     * the report can advertise the one-liner that fixes it. It targets THIS run's
     * checklist (`--repent=latest`), so the fix is scoped to exactly what was reported,
     * with no scope to recompute.
     *
     * @return array<string, string>  sin name => repent command
     */
    private function fixCommands(): array
    {
        $commands = [];

        // Both engines: any Repentable detector advertises the one-liner that fixes its sin.
        foreach ([...Catalog::backend(), ...Catalog::frontend()] as $detector) {
            if (! ($detector instanceof Repentable)) {
                continue;
            }

            $name = $detector->sin()->name();
            $commands[$name] = "vendor/bin/commandments repent --repent=latest --sin={$name}";
        }

        return $commands;
    }

    /**
     * @param  list<Detector>  $detectors  backend (PHP) detectors
     * @param  list<\JesseGall\CodeCommandments\Frontend\Detector>  $frontend  Vue detectors
     * @param  list<string>  $exclude
     */
    private function judge(string $path, bool $pathGiven, array $detectors, array $frontend, array $exclude, Option $checklist, Scope $scope, int $parallel, bool $benchmark, array $fixable, array $scaffoldable): int
    {
        if ($scope->isEmpty()) {
            $this->deleteChecklist($checklist);
            $this->line("\033[32m✓ No changed files to judge.\033[0m");

            return 0;
        }

        // An explicit path is scanned as given; otherwise config.php's declared roots decide what
        // to judge (auto-detected + scaffolded into the config on first run).
        $roots = new SourceRoots()->resolve($path, $pathGiven);

        // Pruned during the WALK, not filtered out of the findings: a monorepo's build output is
        // megabytes this run would otherwise read and parse in full before discarding every sin.
        $config = Config::load($path);
        $excluded = ExcludedPaths::under($path, $config->excludedPaths());

        $progress = new ProgressBar;

        $parseStart = hrtime(true);
        // The scan CARRIES the exemptions it will be judged under — the shipped packages plus any
        // this project's config named — so no detector depends on a static having been written first.
        $codebase = Codebase::scan($roots, $progress->phase('parsing'), excluded: $excluded)
            ->withExemptions(Exemptions::forPackages(...$config->packages()));
        $parseSeconds = (hrtime(true) - $parseStart) / 1e9;

        if ($benchmark) {
            $bench = new Benchmark;
            $judgement = $bench->run($detectors, $codebase);
            $progress->finish();
            fwrite(STDERR, $bench->render($parseSeconds));
        } else {
            $judgement = new DetectorRunner($parallel)->run($detectors, $codebase, $progress);
            $progress->finish();
        }

        // The Vue detectors run over the SAME roots — `judge` is engine-agnostic, so a
        // path with `.vue` files reports its frontend sins alongside the backend ones.
        $judgement = $judgement->merge($this->frontendJudgement($roots, $frontend, $codebase, $excluded, Languages::from($config)));

        $judgement = $judgement->withFindings($this->keep($judgement->findings, $exclude, $scope));

        $skipped = new SkippedRules($judgement->skipped);

        if ($judgement->findings === []) {
            $this->deleteChecklist($checklist);
            $this->line("\033[32m✓ No sins found.\033[0m");

            if ($skipped->isEmpty()) {
                return 0;
            }

            // Nothing found is not the same verdict as nothing to find, when a rule never ran.
            $this->line($skipped->console());

            return self::A_RULE_COULD_NOT_RUN;
        }

        $report = new SinReport($path, $judgement->findings, $fixable, $scaffoldable, $skipped);
        $this->line($report->console());

        foreach ($checklist as $target) {
            @mkdir(dirname($target), 0755, true);
            $this->archive($target);
            file_put_contents($target, $report->checklist());
            $this->line("\033[2m↳ checklist written to {$target} — fix each item, then delete its line\033[0m");
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
        $this->pruneArchives($stem, $ext);
    }

    /**
     * Keep only the {@see KEEP_ARCHIVES} most-recent archives (by write time) for this checklist,
     * deleting the older ones — so `.commandments/` doesn't grow a checklist per run forever.
     */
    private function pruneArchives(string $stem, string $ext): void
    {
        $archives = glob($stem . '-*' . ($ext === '' ? '' : ".{$ext}")) ?: [];

        usort($archives, static fn (string $a, string $b): int => (@filemtime($b) ?: 0) <=> (@filemtime($a) ?: 0));

        foreach (array_slice($archives, self::KEEP_ARCHIVES) as $old) {
            @unlink($old);
        }
    }

    /**
     * Drop findings in excluded files (a `--exclude` fragment) and those out of scope.
     * Exclusion and scope are separate concerns: exclude is the `--exclude` fragments;
     * scope is the changed-file set (which a frozen or generated file never joins).
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
        /**
         * @var array<string, list<string>> $bySkill
         */
        $bySkill = [];

        // The detectors that would actually RUN here, not the shipped catalog: the project's
        // config has already dropped what it disabled and added what it registered, so a project's
        // own rules are listed beside the shipped ones and a silenced one is not listed at all.
        $configured = Config::load()->apply(Catalog::backend(), Catalog::frontend());

        foreach ([...$configured['backend'], ...$configured['frontend']] as $detector) {
            $short = ClassName::short($detector::class);
            // A project's own rule says so, so a reader knows which of the two catalogues it came from.
            $bySkill[$detector->sin()->slug()][] = Custom::owns($detector) ? "{$short} (custom)" : $short;
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
     * @param  list<\JesseGall\CodeCommandments\Frontend\Detector>  $frontend
     */
    private function frontendJudgement(string|array $roots, array $frontend, Codebase $backend, ExcludedPaths $excluded, Languages $languages): Judgement
    {
        if ($frontend === []) {
            return new Judgement;
        }

        $codebase = VueCodebase::scan($roots, excluded: $excluded, languages: $languages);

        // The Bridge is the only place both engines meet: the backend publishes its
        // Data shapes, the frontend detectors that ask for them receive them here.
        $contracts = Bridge::gather($backend, $codebase);

        foreach ($frontend as $detector) {
            if ($detector instanceof ConsumesContracts) {
                $detector->withContracts($contracts);
            }
        }

        $judgement = new Judgement;

        foreach ($frontend as $detector) {
            $sin = $detector->sin();
            $parts = explode('\\', $detector::class);
            $short = end($parts);

            $custom = Custom::owns($detector);

            $attempt = Attempt::of($short, $custom, static fn (): array => $detector->find($codebase));
            $findings = [];

            foreach ($attempt->work as $match) {
                $findings[] = new Finding($short, $sin->slug(), $sin->name(), $match->file(), $match->location(), $match->scope(), custom: $custom);
            }

            $judgement = $judgement->merge(new Judgement($findings, $attempt->skipped === null ? [] : [$attempt->skipped]));
        }

        return $judgement;
    }

    /**
     * @param  list<Detector>  $detectors
     * @return list<Detector>
     */
    private function select(array $detectors, Option $skill, Option $sin): array
    {
        return array_values(array_filter($detectors, static function (RootDetector $candidate) use ($skill, $sin): bool {
            // `--skill` is a LENIENT slug match — `--skill=page` scopes `backend/page-objects`.
            $misses = $skill->isSomeAnd(static fn (string $query): bool => ! str_contains(
                Sin::normalise($candidate->sin()->slug()),
                Sin::normalise($query),
            ));

            if ($misses) {
                return false;
            }

            // `--sin` matches the sin's name OR its skill slug — `--sin=page` scopes the whole group.
            return $sin->isNoneOr(static fn (string $query): bool => $candidate->sin()->scopes($query));
        }));
    }

    /**
     * Is this path excluded — matching any `--exclude` fragment?
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

        return false;
    }

    private function deleteChecklist(Option $checklist): void
    {
        foreach ($checklist as $target) {
            if (is_file($target)) {
                @unlink($target);
            }
        }
    }

    private function line(string $text): void
    {
        fwrite(STDOUT, $text . "\n");
    }
}
