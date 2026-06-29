<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

use JesseGall\CodeCommandments\Detector as BaseDetector;

/**
 * A FRONTEND Sin Detector: finds one sin across the Vue components and names the
 * skill that teaches the fix. The backend's {@see \JesseGall\CodeCommandments\Detectors\Detector},
 * for `.vue` — same base contract, over a Vue {@see Codebase} returning {@see ElementMatch}es.
 */
interface Detector extends BaseDetector
{
    /**
     * @return list<ElementMatch>
     */
    public function find(Codebase $components): array;
}
