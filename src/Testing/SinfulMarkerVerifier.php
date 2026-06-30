<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Codebase as BaseCodebase;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * Our own integration harness: run each detector over the fixture and check its
 * findings against the `#[Sinful]` markers in that same code. A detector passes
 * when it flags every marked sin and nothing else — anything it flags that is
 * not marked is a failure. Adding a test == adding a `#[Sinful]` in the fixture.
 *
 * The backend {@see MarkerVerifier} — the frontend twin is {@see CommentMarkerVerifier}.
 */
final class SinfulMarkerVerifier implements MarkerVerifier
{
    /**
     * @param  Codebase  $codebase
     * @param  list<Detector>  $detectors
     * @return list<DetectorResult>
     */
    public function verify(BaseCodebase $codebase, array $detectors): array
    {
        $markers = SinMarkers::in($codebase);

        return array_map(fn (Detector $detector): DetectorResult => $this->check($codebase, $detector, $markers), $detectors);
    }

    /**
     * @param  list<Marker>  $markers
     */
    private function check(Codebase $codebase, Detector $detector, array $markers): DetectorResult
    {
        $id = $detector::class;
        // A marker names this detector's SIN (`#[Sinful(ArrayBag::class)]`) — or, still,
        // the detector itself — by class. Match either, so the sin-class markers and any
        // legacy detector-class ones both resolve.
        $names = [$id, $detector->sin()::class];
        $sinful = array_values(array_filter($markers, static fn (Marker $m): bool => in_array($m->detector, $names, true)));

        $unexpected = [];
        $hit = [];

        foreach ($detector->find($codebase) as $finding) {
            $index = $this->covering($sinful, $finding->enclosingClassName() ?? '(file)', $finding->enclosingFunctionName());

            if ($index === null) {
                $unexpected[] = $finding->location();

                continue;
            }

            $hit[$index] = true;
        }

        $missed = [];

        foreach ($sinful as $index => $marker) {
            if (! isset($hit[$index])) {
                $missed[] = $marker->location;
            }
        }

        return new DetectorResult($id, $missed, $unexpected);
    }

    /**
     * @param  list<Marker>  $markers
     */
    private function covering(array $markers, string $class, ?string $method): ?int
    {
        foreach ($markers as $index => $marker) {
            if ($marker->covers($class, $method)) {
                return $index;
            }
        }

        return null;
    }
}
