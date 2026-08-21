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
use JesseGall\CodeCommandments\Detectors\CrossFileSet;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Commands;
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

    /**
     * The run a report's advertised `repent` targets: THIS one's checklist, so the fix lands on
     * exactly what was just reported and there is no scope for the reader to recompute.
     */
    private const string REPENT_SCOPE = 'latest';

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

        return $this->judge($options, $detectors, $frontend, $scope, $workspace, Commands::repentable(self::REPENT_SCOPE), Commands::scaffoldable());
    }

    /**
     * @param  list<Detector>  $detectors  backend (PHP) detectors
     * @param  list<\JesseGall\CodeCommandments\Frontend\Detector>  $frontend  Vue detectors
     * @param  array<string, string>  $fixable  sin name => the `repent` command that fixes it
     * @param  array<string, string>  $scaffoldable  sin name => the `scaffold` command for its helper
     */
    private function judge(JudgeOptions $options, array $detectors, array $frontend, Scope $scope, Workspace $workspace, array $fixable, array $scaffoldable): int
    {
        $checklist = $options->checklist;
        if ($scope->isEmpty()) {
            $this->deleteChecklist($checklist);
            $this->line("\033[32m✓ No changed files to judge.\033[0m");

            return 0;
        }

        // An explicit path is scanned as given; otherwise config.php's declared roots decide what
        // to judge (auto-detected + scaffolded into the config on first run).
        $roots = new SourceRoots()->resolve($options->path, $options->pathGiven);

        // Pruned during the WALK, not filtered out of the findings: a monorepo's build output is
        // megabytes this run would otherwise read and parse in full before discarding every sin.
        $config = Config::load($options->path);
        $excluded = ExcludedPaths::under($options->path, $config->excludedPaths());

        $progress = new ProgressBar;

        $parseStart = hrtime(true);
        // The scan CARRIES the exemptions it will be judged under — the shipped packages plus any
        // this project's config named — so no detector depends on a static having been written first.
        $codebase = Codebase::scan($roots, $progress->phase('parsing'), excluded: $excluded)
            ->withExemptions(Exemptions::forPackages(...$config->packages()));
        $parseSeconds = (hrtime(true) - $parseStart) / 1e9;

        // The Vue detectors read the SAME roots — `judge` is engine-agnostic, so a path with `.vue`
        // files is judged by both engines. It is scanned HERE, before either runs, because the
        // Bridge is what both draw on.
        $components = $this->frontendCodebase($roots, $frontend, $detectors, $excluded, Languages::from($config));

        // The two engines meet ONCE, and before either judges: each publishes what it owns and every
        // detector that asked receives the bag. So a BACKEND rule can put a question to the frontend
        // — "is this field read back as blank over there?" — exactly as a frontend rule already puts
        // one to the backend (#510).
        Bridge::publish(Bridge::gather($codebase, $components), [...$detectors, ...$frontend]);

        // WHICH codebase each rule is judged against. A scoped run (`--changes`, `--branch`) reports
        // on a few files but parses the tree, so a rule that reads no further than the file it judges
        // is shown those files alone — its cost tracks the diff, not the tree it came from.
        $beyond = CrossFileSet::forProject($workspace);
        $views = Views::of($codebase, $scope, $beyond);

        if ($options->benchmark) {
            $bench = new Benchmark;
            $judgement = $bench->run($detectors, $views);
            $progress->finish();
            fwrite(STDERR, $bench->render($parseSeconds));
        } else {
            $judgement = new DetectorRunner($options->parallel)->run($detectors, $views, $progress);
            $progress->finish();
        }

        $judgement = $judgement->merge($this->frontendJudgement($components, $frontend, $scope, $beyond));

        $judgement = $judgement->withFindings($this->keep($judgement->findings, $options->exclude, $scope));

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

        $report = new SinReport($options->path, $judgement->findings, $fixable, $scaffoldable, $skipped);
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
     * The Vue codebase this run reads — scanned when a frontend rule will judge it, or when a
     * BACKEND rule asked for what only the frontend can publish. Null when neither is true, so a
     * PHP-only run never pays to scan a tree nothing will read.
     *
     * @param  string|list<string>  $roots
     * @param  list<\JesseGall\CodeCommandments\Frontend\Detector>  $frontend
     * @param  list<Detector>  $backend
     */
    private function frontendCodebase(string|array $roots, array $frontend, array $backend, ExcludedPaths $excluded, Languages $languages): ?VueCodebase
    {
        $asked = array_any($backend, static fn (RootDetector $detector): bool => $detector instanceof ConsumesContracts);

        if ($frontend === [] && ! $asked) {
            return null;
        }

        return VueCodebase::scan($roots, excluded: $excluded, languages: $languages);
    }

    /**
     * The Vue detectors' findings, reduced to the same lightweight {@see Finding}s the backend
     * produces (a Vue {@see ElementMatch} already knows its `file:line` and scope). Each rule reads
     * the same narrowed view of a scoped run the backend's do ({@see Views}).
     *
     * @param  list<\JesseGall\CodeCommandments\Frontend\Detector>  $frontend
     */
    private function frontendJudgement(?VueCodebase $codebase, array $frontend, Scope $scope, CrossFileSet $beyond): Judgement
    {
        if ($frontend === [] || $codebase === null) {
            return new Judgement;
        }

        $views = Views::of($codebase, $scope, $beyond);
        $judgement = new Judgement;

        foreach ($frontend as $detector) {
            $components = $views->for($detector);
            $sin = $detector->sin();
            $parts = explode('\\', $detector::class);
            $short = end($parts);

            $custom = Custom::owns($detector);

            $attempt = Attempt::of($short, $custom, static fn (): array => $detector->find($components));
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
