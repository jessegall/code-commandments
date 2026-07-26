<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Judge;

use JesseGall\CodeCommandments\Support\ClassName;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Bridge\Bridge;
use JesseGall\CodeCommandments\Bridge\ConsumesContracts;
use JesseGall\CodeCommandments\Cli\Scope\Scope;
use JesseGall\CodeCommandments\Cli\Scope\ScopeUnavailable;
use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Packages\Exemptions;
use JesseGall\CodeCommandments\Detector as RootDetector;
use JesseGall\CodeCommandments\Detectors\Catalog;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Sins\Catalog as Sins;
use JesseGall\CodeCommandments\Vue\Codebase as VueCodebase;

use JesseGall\CodeCommandments\Cli\Report\SinReport;
use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Benchmark;
use JesseGall\CodeCommandments\Cli\ProgressBar;
use JesseGall\CodeCommandments\Hooks\HookIO;
use JesseGall\CodeCommandments\Workspace;

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

    public function __construct(private readonly HookIO $io = new HookIO) {}

    public function names(): array
    {
        return ['judge'];
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
        Exemptions::usePackages(...$config->packages());

        // `--ignore-package-requirements` lets one checkout judge ANY project's tree — a
        // package-gated rule (Spatie/Laravel/…) is kept even though THIS project doesn't
        // require the package. For cross-project calibration; user `disable()`s still apply.
        $installed = $options->ignorePackages ? static fn () => true : null;
        $configured = $config->apply(Catalog::backend(), Catalog::frontend(), $installed);
        $detectors = $this->select($configured['backend'], $options->skill, $options->sin);
        $frontend = $this->select($configured['frontend'], $options->skill, $options->sin);

        if ($detectors === [] && $frontend === []) {
            fwrite(STDERR, "No detector matched --skill={$options->skill} --sin={$options->sin}\n");

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
        $checklist = Scope::repent($input->raw()) !== null ? null : $options->checklist;

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
            if ($sin->scaffolds() !== []) {
                $name = $sin->name();
                $commands[$name] = "vendor/bin/commandments scaffold --sin={$name}";
            }
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
            if ($detector instanceof Repentable) {
                $name = $detector->sin()->name();
                $commands[$name] = "vendor/bin/commandments repent --repent=latest --sin={$name}";
            }
        }

        return $commands;
    }

    /**
     * @param  list<Detector>  $detectors  backend (PHP) detectors
     * @param  list<\JesseGall\CodeCommandments\Frontend\Detector>  $frontend  Vue detectors
     * @param  list<string>  $exclude
     */
    private function judge(string $path, bool $pathGiven, array $detectors, array $frontend, array $exclude, ?string $checklist, Scope $scope, int $parallel, bool $benchmark, array $fixable, array $scaffoldable): int
    {
        if ($scope->isEmpty()) {
            $this->deleteChecklist($checklist);
            $this->line("\033[32m✓ No changed files to judge.\033[0m");

            return 0;
        }

        // An explicit path is scanned as given; otherwise config.php's declared roots decide what
        // to judge (auto-detected + scaffolded into the config on first run).
        $roots = new SourceRoots()->resolve($path, $pathGiven);

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
        $findings = array_merge($findings, $this->frontendFindings($roots, $frontend, $codebase));

        $findings = $this->keep($findings, $exclude, $scope);

        if ($findings === []) {
            $this->deleteChecklist($checklist);
            $this->line("\033[32m✓ No sins found.\033[0m");

            return 0;
        }

        $report = new SinReport($path, $findings, $fixable, $scaffoldable);
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
     * @param  list<\JesseGall\CodeCommandments\Frontend\Detector>  $frontend
     * @return list<Finding>
     */
    private function frontendFindings(string|array $roots, array $frontend, Codebase $backend): array
    {
        if ($frontend === []) {
            return [];
        }

        $codebase = VueCodebase::scan($roots);

        // The Bridge is the only place both engines meet: the backend publishes its
        // Data shapes, the frontend detectors that ask for them receive them here.
        $contracts = Bridge::gather($backend, $codebase);

        foreach ($frontend as $detector) {
            if ($detector instanceof ConsumesContracts) {
                $detector->withContracts($contracts);
            }
        }

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
            // `--skill` is a LENIENT slug match — `--skill=page` scopes `backend/page-objects`.
            if ($skill !== null && ! str_contains(Sin::normalise($candidate->sin()->slug()), Sin::normalise($skill))) {
                return false;
            }

            // `--sin` matches the sin's name OR its skill slug — `--sin=page` scopes the whole group.
            return $sin === null || $candidate->sin()->scopes($sin);
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

    private function deleteChecklist(?string $checklist): void
    {
        if ($checklist !== null && is_file($checklist)) {
            @unlink($checklist);
        }
    }

    private function line(string $text): void
    {
        fwrite(STDOUT, $text . "\n");
    }
}
