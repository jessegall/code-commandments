<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Codebase;

/**
 * Verifies a fixture's detectors against its in-code sin markers, whatever the
 * engine: the backend's `#[Sinful]` attributes ({@see SinfulMarkerVerifier}) or the
 * frontend's `<!-- @sin -->` comments ({@see CommentMarkerVerifier}). Both return
 * the same {@see DetectorResult}s, so the shared fixture harness swaps one for the
 * other without caring which engine it is checking.
 */
interface MarkerVerifier
{
    /**
     * @param  list<\JesseGall\CodeCommandments\Detector>  $detectors
     * @return list<DetectorResult>
     */
    public function verify(Codebase $codebase, array $detectors): array;
}
