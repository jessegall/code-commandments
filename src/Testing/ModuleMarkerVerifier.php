<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Detector;
use JesseGall\CodeCommandments\ModuleCodebase;

/**
 * Verifies an engine's detectors against the `@sin Name` comments above the fixture's declarations and
 * statements, in whatever language its modules are — the module twin of {@see CommentMarkerVerifier},
 * sharing its comparison.
 */
final class ModuleMarkerVerifier
{
    /**
     * @param  list<Detector>  $detectors
     * @return list<DetectorResult>
     */
    public function verify(ModuleCodebase $codebase, array $detectors): array
    {
        $marks = DeclarationMarkers::inModules($codebase, 'sin');

        return MarkedFindings::compare(
            $detectors,
            static fn (array $names): array => array_merge(...array_map(static fn (string $name): array => $marks[$name] ?? [], $names)),
            static fn (Detector $detector): array => $detector->find($codebase),
        );
    }
}
