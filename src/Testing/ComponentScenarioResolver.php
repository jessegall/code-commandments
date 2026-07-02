<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Codebase as BaseCodebase;
use JesseGall\CodeCommandments\Located;

/**
 * The frontend {@see ScenarioResolver}: a finding's scenario is its FILE (a component,
 * or a `.ts` type module) — so two findings in one file collapse to one scenario,
 * mirroring {@see ClassScenarioResolver}. Works off {@see Located}, so a template
 * {@see \JesseGall\CodeCommandments\Vue\ElementMatch} and a declaration-space
 * {@see \JesseGall\CodeCommandments\Vue\TypeDeclarationMatch} are scored the same way.
 */
final class ComponentScenarioResolver implements ScenarioResolver
{
    /**
     * @param  list<\JesseGall\CodeCommandments\Frontend\Detector>  $detectors
     * @return array<string, list<array{file: string, source: string}>>
     */
    public function resolve(BaseCodebase $codebase, array $detectors): array
    {
        $scenarios = [];

        foreach ($detectors as $detector) {
            $scenarios[(new \ReflectionClass($detector))->getShortName()] = array_map(
                static fn (Located $match): array => ['file' => $match->file(), 'source' => (string) @file_get_contents($match->file())],
                $detector->find($codebase),
            );
        }

        return $scenarios;
    }
}
