<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Python\Detector;

/**
 * The Python twin of {@see VueFixtureExamples}: each Python detector's worked example, read off the
 * `# @sin`/`# @fixed`/`# @righteous` markers of the Python fixture, as real parsed source.
 */
final class PythonFixtureExamples
{
    /**
     * @param  list<Detector>  $detectors
     * @return array<class-string<Detector>, list<Example>>
     */
    public static function extract(Codebase $codebase, array $detectors): array
    {
        return MarkedExamples::extract($detectors, static fn (string $marker) => MarkedExamples::moduleSources($codebase->modules(), $marker), Language::Python);
    }
}
