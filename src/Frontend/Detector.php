<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Frontend;

use JesseGall\CodeCommandments\Detector as BaseDetector;
use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Vue\Codebase;

/**
 * A FRONTEND Sin Detector: finds one sin across the Vue codebase and names the skill
 * that teaches the fix. The backend's {@see \JesseGall\CodeCommandments\Backend\Detector},
 * for the frontend — same base contract, over a Vue {@see Codebase} returning
 * {@see Located} matches: an {@see \JesseGall\CodeCommandments\Vue\ElementMatch} over
 * the template, or a {@see \JesseGall\CodeCommandments\Vue\TypeDeclarationMatch} over
 * the declared types.
 */
interface Detector extends BaseDetector
{
    /**
     * @return list<Located>
     */
    public function find(Codebase $components): array;
}
