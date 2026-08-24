<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Codebase as BaseCodebase;
use JesseGall\CodeCommandments\Located;

/**
 * The ONE seam where a {@see \JesseGall\CodeCommandments\Detectors\RecurrenceDetector}'s engine-agnostic
 * contract meets the PHP AST: a finding from the other engine, or a codebase that is not this one's, has
 * no fingerprint here. Stated once so no backend recurrence rule re-narrows it and they cannot come to
 * disagree about what counts.
 */
trait GroupsByFingerprint
{
    /**
     * The canonical fingerprint of this finding's recurring shape, or null when it is not countable.
     */
    abstract protected function fingerprint(NodeMatch $finding, Codebase $codebase): ?string;

    final public function groupKey(Located $finding, BaseCodebase $codebase): ?string
    {
        return $finding instanceof NodeMatch && $codebase instanceof Codebase
            ? $this->fingerprint($finding, $codebase)
            : null;
    }
}
