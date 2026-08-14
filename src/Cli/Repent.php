<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Support\Path;

use JesseGall\CodeCommandments\Cli\Scope\PathScope;
use JesseGall\CodeCommandments\Cli\Scope\Scope;
use JesseGall\CodeCommandments\Cli\Scope\ScopeUnavailable;
use JesseGall\CodeCommandments\Detectors\Catalog;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Cli\Report\SkippedRules;
use JesseGall\CodeCommandments\Custom;
use JesseGall\CodeCommandments\Scribes\Converged;
use JesseGall\CodeCommandments\Scribes\Owned;
use JesseGall\CodeCommandments\Scribes\RewriteApplier;
use JesseGall\CodeCommandments\Scribes\ScribeChain;
use JesseGall\CodeCommandments\Scribes\ScribeStep;
use JesseGall\CodeCommandments\Scribes\UnifiedDiff;
use JesseGall\CodeCommandments\WorkingCopy;
use JesseGall\CodeCommandments\Workspace;

use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Config\SourceRoots;
use JesseGall\CodeCommandments\Hooks\Counter;
use JesseGall\CodeCommandments\Hooks\HookIO;
use JesseGall\PhpTypes\Option;

/**
 * Runs the Scribes through {@see ScribeChain}: in-place fixers then component extractors, each
 * re-scanning prior edits. Writes by default; `--dry-run[=FILE]` previews a diff; `--only=NAME`
 * runs matching chain steps. Configurable order via {@see chain} and `.commandments/repent.php`.
 */
final class Repent implements Command
{
    /**
     * The fixpoint cap — a backstop against an oscillating step, far above any real chain depth.
     */
    private const int MAX_SWEEPS = 10;

    /**
     * Show the post-repent feedback nudge once every this many applies — present, not spammy.
     */
    private const int FEEDBACK_INTERVAL = 4;

    /**
     * The exit code for a run where a rewriter broke — it fixed what it could, but not everything it
     * advertises, which is neither success (0) nor a usage error (2). Judge answers 3 the same way.
     */
    private const int A_RULE_COULD_NOT_RUN = 3;

    /**
     * Keep package-gated scribes even when this project lacks the package (cross-project calibration).
     */
    private bool $ignorePackages = false;

    public function __construct(private readonly HookIO $io = new HookIO) {}

    public function names(): array
    {
        return ['repent'];
    }

    public function help(): Help
    {
        return Help::of('Auto-fix sins — run every Scribe: the maintenance rewriters (Spatie Data hints) and each Repentable detector\'s own fix, backend and frontend.')
            ->form('repent [path]', 'apply every fix, re-scanning until it converges (the default — this WRITES)')
            ->form('repent [path] --dry-run[=FILE]', 'preview a unified diff instead, to the screen or a file')
            ->form('repent --only=NAME', 'run ONE rewriter (a Scribe or a Repentable detector, lenient name match)')
            ->adopt(Scope::options())
            ->option('--dry-run[=FILE]', 'preview the rewrite as a unified diff instead of applying it')
            ->option('--only=NAME', 'run one rewriter only (alias: --sin=NAME)')
            ->option('--ignore-package-requirements', 'keep package-gated scribes even if this project lacks the package')
            ->note('Rewrites only within the source roots `judge` reads (config.php\'s paths()), so it never touches '
                . 'tests/ or anything judge would not flag. A broken or incorrect repent result is a BUG — report it with `commandments report`, referencing both the source and the broken output.')
            ->note('A rewriter that BREAKS is dropped so every other fix still applies — one bad scribe used to take '
                . 'the whole command down. The run then names it and exits 3, because it did not auto-fix everything '
                . 'it advertises.');
    }

    public function run(Input $input): int
    {
        $path = rtrim($input->firstArgument()->unwrapOr('.'), '/');
        $only = $input->option('only')->or($input->option('sin'));
        $this->ignorePackages = $input->hasFlag('ignore-package-requirements');

        if (! is_dir($path)) {
            fwrite(STDERR, "Not a directory: {$path}\n");

            return 2;
        }

        try {
            $scope = Scope::fromArgs($input->raw(), $path, Workspace::at($this->io->projectRoot()));
        } catch (ScopeUnavailable $unavailable) {
            fwrite(STDERR, $unavailable->getMessage() . "\n");

            return 2;
        }

        // The SAME roots `judge` reads (config.php's paths()) decide what `repent` rewrites — so it
        // never touches `tests/` (or anything outside the declared roots) that judge wouldn't flag.
        //
        // A given path narrows what may be WRITTEN, never what is READ: the scribes ask cross-file
        // questions ("does anything extend this?"), and answered against one root of a multi-root
        // project they come back wrong and the rewrite emits PHP that will not load (#483). So the
        // parse covers the whole project and a PathScope holds the writing where the user pointed.
        if ($input->firstArgument()->isSome()) {
            $project = $this->io->projectRoot();
            $roots = new SourceRoots()->toParse($project, $path);
            $scope = $scope->and(new PathScope($path));
        } else {
            $roots = new SourceRoots()->resolve($path, false);
        }

        return $input->hasFlag('dry-run')
            ? $this->preview($path, $roots, $scope, $only, $input->option('dry-run'))
            : $this->apply($path, $roots, $scope, $only);
    }

    /**
     * @param  list<string>  $roots  the declared source roots to scan and rewrite
     */
    private function apply(string $path, array $roots, Scope $scope, Option $only): int
    {
        $converged = $this->converge($roots, $scope, $only);
        $written = new RewriteApplier()->apply($converged->files);

        if ($written === []) {
            $this->out(
                "\033[32m✓ Nothing to AUTO-FIX here.\033[0m \033[2mThis is NOT \"no sins\" — it only means no scribe could\n"
                . "  rewrite this scope. A sin `judge` flags without an auto-fixer still stands; fix it BY HAND at its\n"
                . "  source per its skill. Run `judge` to see what remains.\033[0m\n",
            );

            return $this->reportSkipped($converged);
        }

        $count = count($written);
        $this->out("\033[32m✓ Repented {$count} " . ($count === 1 ? 'file' : 'files') . ".\033[0m\n");

        foreach ($written as $file) {
            $this->out('  ' . Path::relative($file, $path) . "\n");
        }

        $this->scaffoldConstructs($converged->files, $written);
        $this->inviteFeedback();

        return $this->reportSkipped($converged);
    }

    /**
     * Name the rewriters that could not run, and answer with the exit code the run has earned: a
     * repent that skipped one has NOT auto-fixed everything it advertises, whatever it managed.
     */
    private function reportSkipped(Converged $converged): int
    {
        $skipped = new SkippedRules($converged->skipped);

        if ($skipped->isEmpty()) {
            return 0;
        }

        $this->out($skipped->console() . "\n");

        return self::A_RULE_COULD_NOT_RUN;
    }

    /**
     * Invite the agent to judge the auto-fix it just ran and report anything wrong — a rate-limited
     * nudge (issue #306) that turns each repent into a feedback moment, so a broken/awkward rewrite or
     * a rule gap gets filed early instead of silently worked around. Shown on the first apply and every
     * {@see FEEDBACK_INTERVAL} after, counted by the session's `repent-feedback` {@see Counter}.
     */
    private function inviteFeedback(): void
    {
        $counter = Counter::named(
            Workspace::at($this->io->projectRoot()),
            'repent-feedback',
            'rate-limits the post-repent feedback nudge',
            every: self::FEEDBACK_INTERVAL,
        );

        if (! $counter->firstThenEvery()) {
            return;
        }

        $this->out(
            "\033[2m↳ Did that auto-fix do the right thing? If `repent` produced anything broken, incomplete, or\n"
            . "  awkward — or you noticed a rule gap or false positive — file it (don't just work around it):\n"
            . "  `commandments report --reason=\"…\" --ref=path:line` (referencing the source AND the bad output),\n"
            . "  or `commandments feature-request --title=\"…\" --reason=\"…\"`.\033[0m\n",
        );
    }

    /**
     * What the run wrote into COMPONENTS — the only files a Vue construct can be rendered from. A
     * backend rewrite cannot use one, so its content is not evidence that a stub is needed.
     *
     * @param  array<string, string>  $converged  path => final content
     * @param  list<string>  $written
     */
    private function componentSource(array $converged, array $written): string
    {
        $components = array_filter($written, static fn (string $file): bool => str_ends_with($file, '.vue'));

        return implode("\n", array_map(static fn (string $file): string => $converged[$file] ?? '', $components));
    }

    /**
     * Generate the reusable constructs the fixes just applied depend on. A fix that rewrites toward
     * a scaffolded helper (a `v-if` chain into `<SwitchCase>`) needs that helper to exist — so if a
     * written file now uses one, {@see Scaffold} mints it (idempotent, so an existing one is left
     * alone). `scaffold` and `repent` compose; running them in one command means a repented tree
     * actually compiles instead of referencing a construct the project hasn't got yet.
     *
     * @param  array<string, string>  $converged  path => final content
     * @param  list<string>  $written
     */
    private function scaffoldConstructs(array $converged, array $written): void
    {
        $output = $this->componentSource($converged, $written);

        foreach (Catalog::all() as $detector) {
            if (! $detector instanceof Repentable) {
                continue;
            }

            foreach ($detector->sin()->scaffolds() as $scaffold) {
                // The construct is used when a rewritten COMPONENT renders it as an element —
                // `<SwitchCase`. Merely naming it is not use: a PHP file that mentions the construct
                // (the scribe that writes it, a comment, a test) once minted a Vue stub into a project
                // with no Vue in it at all (#407).
                if (! str_contains($output, '<' . pathinfo($scaffold->path, PATHINFO_FILENAME))) {
                    continue;
                }

                new Scaffold()->run(Input::of('scaffold', ['--sin=' . $detector->sin()->name()]));

                break;
            }
        }
    }

    /**
     * @param  list<string>  $roots  the declared source roots to scan and rewrite
     */
    private function preview(string $path, array $roots, Scope $scope, Option $only, Option $file): int
    {
        // The converged result is diffed against pristine disk (converge writes nothing), so the
        // dry-run is exactly what an apply would produce — a fixpoint, not a single sweep.
        $converged = $this->converge($roots, $scope, $only);
        $diff = new UnifiedDiff()->of($converged->files, $path);

        if ($diff === '') {
            $this->out(
                "\033[32m✓ Nothing to AUTO-FIX here.\033[0m \033[2mThis is NOT \"no sins\" — it only means no scribe could\n"
                . "  rewrite this scope. A sin `judge` flags without an auto-fixer still stands; fix it BY HAND at its\n"
                . "  source per its skill. Run `judge` to see what remains.\033[0m\n",
            );

            return $this->reportSkipped($converged);
        }

        foreach ($file as $target) {
            file_put_contents($target, $diff);
            $this->out("\033[2m↳ dry-run diff written to {$target}\033[0m\n");
            $this->reportHint();

            return $this->reportSkipped($converged);
        }

        $this->out($diff);
        $this->reportHint();

        return $this->reportSkipped($converged);
    }

    /**
     * The point-of-action nudge on a dry-run: the agent is looking at the exact diff an apply would make,
     * so this is where to say "judge THIS diff, and if a fix is wrong report it — don't just discard it."
     * It explicitly warns against skipping from a remembered "known-bad" verdict: the auto-fixers change
     * every release, so a fix that broke last week may be fixed now — the only source of truth is the diff
     * in front of you. Unconditional here (unlike the rate-limited post-apply {@see inviteFeedback}).
     */
    private function reportHint(): void
    {
        $this->out(
            "\033[2m↳ Judge THIS diff on its own. The auto-fixers change every release, so never skip a repent from a\n"
            . "  past result or a remembered \"this fix is broken\" — and don't record one as permanently broken;\n"
            . "  re-run `--dry-run` and read the CURRENT output. If a fix is genuinely broken, incomplete, or wrong,\n"
            . "  file it (don't just discard it): `commandments report --reason=\"…\" --ref=path:line` (source AND bad output).\033[0m\n",
        );
    }

    /**
     * Run the chain to a FIXPOINT over $path and return the converged `path => content` map,
     * WITHOUT touching disk. Each sweep runs every step reading through the accumulated
     * {@see WorkingCopy} overlay — so a step sees prior steps' AND prior sweeps' edits (and any
     * file an extractor created) — until a whole sweep adds nothing new. A later step can
     * enable an earlier one (a scribe that mints `::from()`/`::collect()` call sites the hint
     * scribe then documents), so one sweep isn't always enough; the loop settles that in a
     * single command instead of leaving residue for a second `repent`. Capped so an oscillating
     * step can't spin forever.
     *
     * @param  list<string>  $roots  the declared source roots to scan and rewrite
     */
    private function converge(array $roots, Scope $scope, Option $only): Converged
    {
        $steps = array_values($this->chain($only)->steps());
        $overlay = new WorkingCopy();
        $progress = new ProgressBar();
        $skipped = [];

        for ($sweep = 0; $sweep < self::MAX_SWEEPS; $sweep++) {
            $before = $overlay->changes();

            foreach ($steps as $index => $step) {
                $sweepLabel = $sweep === 0 ? '' : ' (sweep ' . ($sweep + 1) . ')';
                $progress->track($index, count($steps), 'repenting' . $sweepLabel . ' · ' . $step->name());

                // A rewriter that BREAKS costs its own fixes and nothing else. It used to take the whole
                // command with it, so one bad scribe meant no sin in the run could be auto-fixed (#456).
                $attempt = Attempt::of($step->name(), $this->isCustom($step), static fn (): array => $step->run($roots, $scope, $overlay));

                if ($attempt->skipped !== null) {
                    // It will meet the same shape on the next sweep, so drop it rather than fail it ten
                    // times over: the reader needs the failure once, not once per sweep.
                    unset($steps[$index]);
                    $skipped[] = $attempt->skipped;

                    continue;
                }

                // The chain rule: a step's rewrite is applied only if EVERY existing file it would touch
                // is a target. One frozen / out-of-scope member drops the whole connected rewrite, so a
                // repent never half-applies or writes into a file this run must not touch.
                if ($scope->permits(array_keys($attempt->work))) {
                    $overlay = $overlay->with($attempt->work);
                }
            }

            if ($overlay->changes() === $before) {
                $progress->finish();

                return new Converged($overlay->changes(), $skipped);
            }
        }

        $progress->finish();
        fwrite(STDERR, "\033[33m⚠ repent did not settle within " . self::MAX_SWEEPS . " sweeps; applying what converged.\033[0m\n");

        return new Converged($overlay->changes(), $skipped);
    }

    /**
     * Is the rule this step runs one the PROJECT wrote? A step is ours; the scribe or detector inside
     * it need not be, and a failure must be attributed to whoever can fix it.
     */
    private function isCustom(ScribeStep $step): bool
    {
        return $step instanceof Owned && Custom::owns($step->owner());
    }

    /**
     * The chain to run — the default ordering, handed to the consumer's
     * `.commandments/repent.php` (a `fn (ScribeChain): ScribeChain`) to reorder if they
     * have one, then narrowed by `--only`.
     */
    private function chain(Option $only): ScribeChain
    {
        $chain = ScribeChain::default($this->ignorePackages ? static fn () => true : null);
        $config = Workspace::at($this->io->projectRoot())->shared('repent.php');

        if (is_file($config)) {
            $customise = require $config;

            if (is_callable($customise) && ($customised = $customise($chain)) instanceof ScribeChain) {
                $chain = $customised;
            }
        }

        return $only->mapOr($chain, static fn (string $match): ScribeChain => $chain->matching($match));
    }


    private function out(string $text): void
    {
        fwrite(STDOUT, $text);
    }
}
