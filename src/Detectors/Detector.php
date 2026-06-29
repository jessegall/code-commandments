<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Detector as BaseDetector;

/**
 * A BACKEND Sin Detector: finds the locations of one skill's sins in the PHP AST
 * and points back at that skill. It carries no fix and no rubric — the skill
 * teaches; the detector only finds. Identified by its class; markers reference it
 * via `::class`. The frontend twin is {@see \JesseGall\CodeCommandments\Vue\Detector}.
 */
interface Detector extends BaseDetector
{
    /**
     * Every location this detector considers a sin.
     *
     * @return list<NodeMatch>
     */
    public function find(Codebase $codebase): array;
}
