<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;
use JesseGall\CodeCommandments\Detectors\Sharded;

/**
 * The `--benchmark` profiler (hidden): runs every detector ONE AT A TIME, timing
 * each `find()` and watching memory, so you can see exactly where a judge run
 * spends itself and decide what to parallelize. It produces the SAME findings as
 * {@see DetectorRunner} — order doesn't change what's found — but trades the
 * parallel pool for honest per-detector numbers (a forked worker can't be timed
 * from the parent). A {@see Sharded} detector also reports its shard count: the
 * unit of parallel work, and the lever for spreading a heavy one across cores.
 *
 * The table goes to STDERR, so STDOUT stays the clean findings/checklist.
 */
final class Benchmark
{
    /** @var list<array{name: string, seconds: float, matches: int, shards: ?int, bytes: int}> */
    private array $records = [];

    /**
     * Run each detector timed and return its findings (identical set to the parallel
     * runner). Side effect: records the per-detector profile for {@see render}.
     *
     * @param  list<Detector>  $detectors
     * @return list<Finding>
     */
    public function run(array $detectors, Codebase $codebase): array
    {
        $findings = [];

        foreach ($detectors as $detector) {
            $short = $this->shortName($detector);

            $before = memory_get_usage();
            $start = hrtime(true);

            $matches = $detector->find($codebase);

            $seconds = (hrtime(true) - $start) / 1e9;
            $bytes = memory_get_usage() - $before;
            $shards = $detector instanceof Sharded ? count($detector->shards($codebase)) : null;

            $this->records[] = [
                'name' => $short,
                'seconds' => $seconds,
                'matches' => count($matches),
                'shards' => $shards,
                'bytes' => $bytes,
            ];

            $skill = $detector->skill();

            foreach ($matches as $match) {
                $findings[] = new Finding($short, $skill, $match->file->path, $match->location(), $match->scope());
            }
        }

        return $findings;
    }

    /**
     * The profile table, sorted slowest-first — what to optimize is whatever's at
     * the top. `shards` is the parallel-work count for a {@see Sharded} detector
     * (`·` means not sharded — one indivisible task pinning one core).
     */
    public function render(float $parseSeconds): string
    {
        $rows = $this->records;
        usort($rows, static fn (array $a, array $b): int => $b['seconds'] <=> $a['seconds']);

        $total = array_sum(array_column($rows, 'seconds'));

        $lines = [];
        $lines[] = '';
        $lines[] = sprintf('  parse: %6.2fs   detect: %6.2fs   (sequential, profiling)', $parseSeconds, $total);
        $lines[] = sprintf('  %-42s %9s %6s %7s %8s', 'detector', 'time', '%', 'matches', 'shards');
        $lines[] = '  ' . str_repeat('─', 76);

        foreach ($rows as $row) {
            $lines[] = sprintf(
                '  %-42s %8.3fs %5.1f %7d %8s   %s',
                $row['name'],
                $row['seconds'],
                $total > 0 ? $row['seconds'] / $total * 100 : 0,
                $row['matches'],
                $row['shards'] === null ? '·' : (string) $row['shards'],
                $this->bytes($row['bytes']),
            );
        }

        return implode("\n", $lines) . "\n";
    }

    private function bytes(int $bytes): string
    {
        if (abs($bytes) < 1024 * 1024) {
            return sprintf('%+.0fK', $bytes / 1024);
        }

        return sprintf('%+.1fM', $bytes / 1024 / 1024);
    }

    private function shortName(Detector $detector): string
    {
        $parts = explode('\\', $detector::class);

        return end($parts);
    }
}
