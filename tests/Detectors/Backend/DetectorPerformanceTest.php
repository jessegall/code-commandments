<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Catalog;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * A regression guard against a pathologically slow detector — the kind that rebuilds a
 * whole-codebase index per candidate (O(candidates × files)) and comes to dominate a real judge
 * run. It times every backend detector over the fixture and fails if one is a runtime ANOMALY: far
 * above the others. Relative to the median (not an absolute wall-clock) so it isn't machine- or
 * CI-speed dependent, with a floor so a fast run's noise can't trip it.
 */
final class DetectorPerformanceTest extends TestCase
{
    /** A detector may take at most this multiple of the median detector's time… */
    private const float MULTIPLE_OF_MEDIAN = 20.0;

    /** …but never fails under this floor, so millisecond-scale noise on a fast box can't trip it. */
    private const float FLOOR_SECONDS = 1.0;

    public function test_no_backend_detector_is_a_runtime_anomaly(): void
    {
        $codebase = Codebase::scan(dirname(__DIR__, 2) . '/Fixtures/backend');

        $times = [];

        foreach (Catalog::backend() as $detector) {
            $start = hrtime(true);
            $detector->find($codebase);
            $times[new ReflectionClass($detector)->getShortName()] = (hrtime(true) - $start) / 1e9;
        }

        $ceiling = max($this->median($times) * self::MULTIPLE_OF_MEDIAN, self::FLOOR_SECONDS);

        foreach ($times as $name => $seconds) {
            $this->assertLessThan(
                $ceiling,
                $seconds,
                sprintf('%s took %.3fs — a runtime anomaly (ceiling %.3fs). Likely an O(n²) rebuild per candidate; memoise it.', $name, $seconds, $ceiling),
            );
        }
    }

    /**
     * @param  array<string, float>  $times
     */
    private function median(array $times): float
    {
        $values = array_values($times);
        sort($values);

        return $values[intdiv(count($values), 2)] ?? 0.0;
    }
}
