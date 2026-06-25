<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;

/**
 * A Sin Detector: finds the locations of one skill's sins and points back at
 * that skill. It carries no fix and no rubric — the skill teaches; the detector
 * only finds. Identified by its class; markers reference it via `::class`.
 */
interface Detector
{
    /**
     * The skill slug a finding points the agent at ("read this").
     */
    public function skill(): string;

    /**
     * Every location this detector considers a sin.
     *
     * @return list<NodeMatch>
     */
    public function find(Codebase $codebase): array;
}
