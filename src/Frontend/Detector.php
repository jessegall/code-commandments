<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Frontend;

use JesseGall\CodeCommandments\Detector as BaseDetector;
use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Vue\ElementMatch;

/**
 * A FRONTEND Sin Detector: finds one sin across the Vue components and names the
 * skill that teaches the fix. The backend's {@see \JesseGall\CodeCommandments\Backend\Detector},
 * for `.vue` — same base contract, over a Vue {@see Codebase} returning {@see ElementMatch}es.
 */
interface Detector extends BaseDetector
{
    /**
     * @return list<ElementMatch>
     */
    public function find(Codebase $components): array;
}
