<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use Closure;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Concurrency\Fork;
use JesseGall\CodeCommandments\Detectors\Detector;
use JesseGall\CodeCommandments\Detectors\Sharded;

/**
 * Runs the detectors over a parsed codebase and returns lightweight findings —
 * everything the report needs and nothing that holds an AST node, so a finding can
 * cross a process boundary.
 *
 * The work is a flat pool of TASKS, run in parallel by {@see Fork::map} over the
 * copy-on-write-shared AST. A plain detector is one task (`find()` → findings); a
 * {@see Sharded} detector contributes one task PER shard, so a single expensive
 * detector (e.g. a call-graph blast-radius check over hundreds of candidates)
 * spreads across the whole pool instead of pinning one core behind the others.
 * Each task returns already-flattened {@see Finding}s, so no AST node is ever
 * serialized. `--parallel=1`, or a build without `pcntl`/socket pairs, runs the
 * same tasks sequentially.
 */
final class DetectorRunner
{
    public function __construct(private readonly int $parallel) {}

    /**
     * @param  list<Detector>  $detectors
     * @return list<Finding>
     */
    public function run(array $detectors, Codebase $codebase, ProgressBar $progress): array
    {
        $tasks = $this->tasks($detectors, $codebase);

        $progress->start(count($tasks));

        $byTask = Fork::map(
            $tasks,
            static fn (Closure $task): array => $task(),
            $this->parallel >= 1 ? $this->parallel : null,
            static function (int $done) use ($progress): void {
                for ($i = 0; $i < $done; $i++) {
                    $progress->advance();
                }
            },
        );

        $findings = [];

        foreach ($byTask as $taskFindings) {
            foreach ($taskFindings as $finding) {
                $findings[] = $finding;
            }
        }

        return $findings;
    }

    /**
     * Flatten the detectors into the unit of parallel work: one task per plain
     * detector, one task per shard of a {@see Sharded} one. Every task returns
     * serializable {@see Finding}s (the AST→Finding reduction happens INSIDE the
     * task, so it runs in the worker and only strings come back).
     *
     * @param  list<Detector>  $detectors
     * @return list<Closure(): list<Finding>>
     */
    private function tasks(array $detectors, Codebase $codebase): array
    {
        $tasks = [];

        foreach ($detectors as $detector) {
            $short = $this->shortName($detector);
            $skill = $detector->skill();

            if ($detector instanceof Sharded) {
                foreach ($detector->shards($codebase) as $shard) {
                    $tasks[] = static fn (): array => self::findings($short, $skill, $shard());
                }

                continue;
            }

            $tasks[] = static fn (): array => self::findings($short, $skill, $detector->find($codebase));
        }

        return $tasks;
    }

    /**
     * Reduce a detector's matches to lightweight findings (no AST node survives —
     * only the strings the report needs).
     *
     * @param  list<\JesseGall\CodeCommandments\Ast\NodeMatch>  $matches
     * @return list<Finding>
     */
    private static function findings(string $detector, string $skill, array $matches): array
    {
        $findings = [];

        foreach ($matches as $match) {
            $findings[] = new Finding($detector, $skill, $match->file->path, $match->location(), $match->scope());
        }

        return $findings;
    }

    private function shortName(Detector $detector): string
    {
        $parts = explode('\\', $detector::class);

        return end($parts);
    }
}
