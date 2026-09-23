<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Detector;
use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\ModuleCodebase;

/**
 * The module twin of {@see VueFixtureExamples}: each detector's worked example, read off the
 * `@sin`/`@fixed`/`@righteous` markers of a fixture read as parsed modules, as real source.
 */
final class ModuleFixtureExamples
{
    /**
     * @param  list<Detector>  $detectors
     * @return array<class-string<Detector>, list<Example>>
     */
    public static function extract(ModuleCodebase $codebase, array $detectors, Language $language): array
    {
        return MarkedExamples::extract($detectors, static fn (string $marker) => MarkedExamples::moduleSources($codebase->modules(), $marker), $language);
    }
}
