<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use Closure;
use JesseGall\CodeCommandments\Detector;
use JesseGall\CodeCommandments\Located;

/**
 * What every comment-marked fixture checks, whatever language its markers are written in: each detector
 * against the locations marked for it — by its sin's short name or its own — reporting what is marked
 * but not flagged (a hole) and what is flagged but not marked (a false positive, or a sin nobody marked).
 */
final class MarkedFindings
{
    /**
     * @param  list<Detector>  $detectors
     * @param  Closure(list<string>): list<string>  $marked  the locations marked with any of the names given
     * @param  Closure(Detector): list<Located>  $found  what the detector flags in the fixture
     * @return list<DetectorResult>
     */
    public static function compare(array $detectors, Closure $marked, Closure $found): array
    {
        $results = [];

        foreach ($detectors as $detector) {
            $name = new \ReflectionClass($detector)->getShortName();
            $expected = $marked([$name, new \ReflectionClass($detector->sin())->getShortName()]);
            $flagged = array_map(static fn (Located $match): string => $match->location(), $found($detector));

            $results[] = new DetectorResult(
                $name,
                array_values(array_diff($expected, $flagged)),
                array_values(array_diff($flagged, $expected)),
            );
        }

        return $results;
    }
}
