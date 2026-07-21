<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Judge;

/**
 * One sin a detector flagged, reduced to the strings the report needs and nothing
 * that holds an AST node — so it serializes across the {@see DetectorRunner} fork
 * boundary. The detector is the SHORT name; `skill` is the slug the report groups by;
 * `sin` is the `--sin=` id; `location` is `path:line`; `scope` is `Class::method`.
 */
final class Finding
{
    /**
     * @param  list<string>  $twins  the OTHER occurrences this finding was bucketed with — non-empty only
     *                               for a {@see \JesseGall\CodeCommandments\Detectors\RecurrenceDetector},
     *                               whose whole verdict is "this shape RECURS". Without them the reader
     *                               cannot see what it recurs WITH, and judges the site alone — which
     *                               reads as a false positive whenever the twin lives in another file.
     */
    public function __construct(
        public readonly string $detector,
        public readonly string $skill,
        public readonly string $sin,
        public readonly string $file,
        public readonly string $location,
        public readonly string $scope,
        public readonly array $twins = [],
    ) {}
}
