<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Custom;

use JesseGall\CodeCommandments\Cli\Judge\DetectorAttempt;

use JesseGall\CodeCommandments\Support\ClassName;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Sharded;

use JesseGall\CodeCommandments\Cli\Judge\DetectorRunner;
use JesseGall\CodeCommandments\Finding;
/**
 * The profiler that runs every detector one at a time, timing each `find()` call
 * and memory usage. Produces the same findings as the parallel runner but with
 * honest per-detector numbers. Table goes to STDERR, findings to STDOUT.
 */
final class Benchmark
{
    /**
     * @var list<DetectorProfile>
     */
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
            $short = ClassName::short($detector::class);

            $before = memory_get_usage();
            $start = hrtime(true);

            $matches = DetectorAttempt::of($short, Custom::owns($detector), static fn (): array => $detector->find($codebase));

            $seconds = (hrtime(true) - $start) / 1e9;
            $bytes = memory_get_usage() - $before;
            $shards = $detector instanceof Sharded ? count($detector->shards($codebase)) : null;

            $this->records[] = new DetectorProfile($short, $seconds, count($matches), $bytes, $shards);

            $sin = $detector->sin();

            foreach ($matches as $match) {
                $findings[] = new Finding($short, $sin->slug(), $sin->name(), $match->file(), $match->location(), $match->scope());
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
        usort($rows, static fn (DetectorProfile $a, DetectorProfile $b): int => $b->seconds <=> $a->seconds);

        $total = array_sum(array_map(static fn (DetectorProfile $row): float => $row->seconds, $rows));

        $lines = [];
        $lines[] = '';
        $lines[] = sprintf('  parse: %6.2fs   detect: %6.2fs   (sequential, profiling)', $parseSeconds, $total);
        $lines[] = sprintf('  %-42s %9s %6s %7s %8s', 'detector', 'time', '%', 'matches', 'shards');
        $lines[] = '  ' . str_repeat('─', 76);

        foreach ($rows as $row) {
            $lines[] = sprintf(
                '  %-42s %8.3fs %5.1f %7d %8s   %s',
                $row->name,
                $row->seconds,
                $row->shareOf($total),
                $row->matches,
                $row->shardsColumn(),
                $this->bytes($row->bytes),
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
}
