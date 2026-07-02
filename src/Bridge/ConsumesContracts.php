<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Bridge;

/**
 * The REQUEST side of the {@see Bridge}: a detector that reads cross-cutting
 * {@see Contract}s another engine published. The neutral runner injects the gathered
 * {@see Contracts} before `find()`, exactly as it reads {@see \JesseGall\CodeCommandments\Detectors\Repentable}
 * to route a scribe — an opt-in capability, not a change to the detector contract.
 *
 * A consumer stays a single-engine detector: it filters the bag to the kind it wants
 * ({@see Contracts::ofType}) and never learns which engine produced it.
 */
interface ConsumesContracts
{
    /**
     * Receive the contracts published this run — called once, before `find()`.
     */
    public function withContracts(Contracts $contracts): void;
}
