<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Judge;

use JesseGall\CodeCommandments\Finding;

use JesseGall\CodeCommandments\Custom;
use JesseGall\CodeCommandments\Support\ClassName;

use Closure;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Concurrency\Fork;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\RecurrenceDetector;

use JesseGall\CodeCommandments\Cli\Attempt;
use JesseGall\CodeCommandments\Cli\ProgressBar;
/**
 * Runs detectors in parallel over a shared AST, returning serializable findings (AST nodes
 * stay behind in workers). Work is divided into one task per detector, executed by
 * the {@see Fork} pool it is handed.
 */
final class DetectorRunner
{
    public function __construct(private readonly int $parallel, private readonly Fork $fork = new Fork) {}

    /**
     * @param  list<Detector>  $detectors
     */
    public function run(array $detectors, Views $views, ProgressBar $progress): Judgement
    {
        $tree = $views->wholeTreeFor($detectors);

        if ($tree instanceof Codebase) {
            // Build the call graph AND the value-flow graph ONCE in the parent so forked workers
            // inherit them copy-on-write, instead of each rebuilding them (or each cross-file
            // detector re-scanning the tree per query). A scoped run that shows no rule the tree
            // never builds either.
            $tree->index()->warm();
            $tree->valueFlow()->warm();
        }

        $tasks = $this->tasks($detectors, $views);

        $progress->start(count($tasks));

        $byTask = $this->fork->map(
            $tasks,
            static fn (Closure $task): Attempt => $task(),
            $this->parallel >= 1 ? $this->parallel : null,
            static function (int $done) use ($progress): void {
                for ($i = 0; $i < $done; $i++) {
                    $progress->advance();
                }
            },
        );

        $judgement = new Judgement;

        // A rule that broke in a WORKER is still a rule that did not run, so its skip crosses
        // back with its (empty) findings rather than dying with the process that met it.
        foreach ($byTask as $attempt) {
            $judgement = $judgement->merge(new Judgement(
                $attempt->work,
                $attempt->skipped === null ? [] : [$attempt->skipped],
            ));
        }

        return $judgement;
    }

    /**
     * One task per detector — the unit of parallel work, each over the codebase {@see Views} says
     * that rule is judged against. Every task returns a serializable {@see Attempt} of
     * {@see Finding}s (the AST→Finding reduction happens INSIDE the task, so it runs in the worker
     * and only strings come back).
     *
     * @param  list<Detector>  $detectors
     * @return list<Closure(): Attempt>
     */
    private function tasks(array $detectors, Views $views): array
    {
        $tasks = [];

        foreach ($detectors as $detector) {
            $codebase = $views->for($detector);
            $short = ClassName::short($detector::class);
            $sin = $detector->sin();
            $custom = Custom::owns($detector); // Resolved in the PARENT: a worker's finding must already
            // know whose rule it came from, and the answer never depends on where it ran.

            $tasks[] = static fn () => Attempt::of($short, $custom, static fn () => self::findings(
                $short,
                $sin->slug(),
                $sin->name(),
                $detector->find($codebase),
                $detector,
                $codebase,
                $custom,
            ));
        }

        return $tasks;
    }

    /**
     * Reduce a detector's matches to lightweight findings (no AST node survives —
     * only the strings the report needs).
     *
     * @param  list<\JesseGall\CodeCommandments\Located>  $matches
     * @return list<Finding>
     */
    private static function findings(string $detector, string $skill, string $sin, array $matches, Detector $rule, Codebase $codebase, bool $custom = false): array
    {
        $buckets = self::buckets($matches, $rule, $codebase);
        $findings = [];

        foreach ($matches as $match) {
            $location = $match->location();
            $twins = array_values(array_diff($buckets[spl_object_id($match)] ?? [], [$location]));

            $findings[] = new Finding($detector, $skill, $sin, $match->file(), $location, $match->scope(), $twins, $custom);
        }

        return $findings;
    }

    /**
     * Every match's FELLOW occurrences, keyed by match — the sibling locations a recurrence verdict rests
     * on. Only a {@see RecurrenceDetector} has them: it alone declares the fingerprint that decides which
     * findings are the same shape, so re-reading {@see RecurrenceDetector::groupKey} here is the honest
     * way to recover the bucket the verdict came from. Every other detector judges a site on its own.
     *
     * @param  list<\JesseGall\CodeCommandments\Ast\NodeMatch>  $matches
     * @return array<int, list<string>>  spl_object_id(match) => the locations in its bucket
     */
    private static function buckets(array $matches, Detector $rule, Codebase $codebase): array
    {
        if (! $rule instanceof RecurrenceDetector) {
            return [];
        }

        $byKey = [];
        $keyOf = [];

        foreach ($matches as $match) {
            $key = $rule->groupKey($match, $codebase);

            if ($key !== null) {
                $byKey[$key][] = $match->location();
                $keyOf[spl_object_id($match)] = $key;
            }
        }

        return array_map(static fn (string $key): array => $byKey[$key], $keyOf);
    }
}
