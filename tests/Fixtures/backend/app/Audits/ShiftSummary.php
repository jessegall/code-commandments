<?php

namespace Shop\Audits;

use JesseGall\CodeCommandments\Sins\Backend\UnusedParameter;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Sums up the depot a counted shift ran in for the audit log, falling back to a stock line when it has no name.
 */
final class ShiftSummary
{
    public function line(AuditedDepot $depot): string
    {
        return $depot->name === '' ? $this->fallbackLine($depot) : 'counted at ' . $depot->name;
    }

    #[Sinful(UnusedParameter::class)]
    private function fallbackLine(AuditedDepot $depot): string
    {
        return 'counted at an unnamed depot';
    }
}
