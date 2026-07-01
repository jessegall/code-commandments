<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Codebase as BaseCodebase;
use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Frontend\Detector;
use JesseGall\CodeCommandments\Vue\ElementMatch;

/**
 * The frontend {@see ScenarioResolver}: a finding's scenario is its enclosing
 * COMPONENT (the frontend's "class"), so two findings in one `.vue` collapse to one
 * scenario — mirroring {@see ClassScenarioResolver}.
 */
final class ComponentScenarioResolver implements ScenarioResolver
{
    /**
     * @param  Codebase  $codebase
     * @param  list<Detector>  $detectors
     * @return array<string, list<array{file: string, source: string}>>
     */
    public function resolve(BaseCodebase $codebase, array $detectors): array
    {
        $scenarios = [];

        foreach ($detectors as $detector) {
            $scenarios[(new \ReflectionClass($detector))->getShortName()] = array_map(
                static fn (ElementMatch $match): array => ['file' => $match->file(), 'source' => $match->sfc->source],
                $detector->find($codebase),
            );
        }

        return $scenarios;
    }
}
