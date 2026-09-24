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
     * @param  array<class-string<Detector>, array<string, array<int, string>>>  $groups  each recurring rule's findings by file and line, with the group each recurs in
     * @return array<class-string<Detector>, list<Example>>
     */
    public static function extract(ModuleCodebase $codebase, array $detectors, Language $language, array $groups = []): array
    {
        return MarkedExamples::extract($detectors, static fn (string $marker) => MarkedExamples::moduleSources($codebase->modules(), $marker), $language, $groups);
    }
}
