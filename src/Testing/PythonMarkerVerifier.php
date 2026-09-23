<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Python\Detector;

/**
 * Verifies the Python detectors against the `# @sin Name` comments above the fixture's declarations and
 * statements — the Python twin of {@see CommentMarkerVerifier}, sharing its comparison.
 */
final class PythonMarkerVerifier
{
    /**
     * @param  list<Detector>  $detectors
     * @return list<DetectorResult>
     */
    public function verify(Codebase $codebase, array $detectors): array
    {
        $marks = DeclarationMarkers::inPython($codebase, 'sin');

        return MarkedFindings::compare(
            $detectors,
            static fn (array $names): array => array_merge(...array_map(static fn (string $name): array => $marks[$name] ?? [], $names)),
            static fn (Detector $detector): array => $detector->find($codebase),
        );
    }
}
