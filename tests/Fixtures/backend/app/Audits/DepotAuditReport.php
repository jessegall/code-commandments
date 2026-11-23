<?php

namespace Shop\Audits;

use JesseGall\CodeCommandments\Sins\Backend\UnusedPrivateMember;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * The audit report of one depot; the trait it uses reads the depot it keeps.
 */
final class DepotAuditReport
{
    use ReadsAuditedDepot;

    #[Righteous(UnusedPrivateMember::class)]
    private AuditedDepot $depot;

    public function __construct(AuditedDepot $depot)
    {
        $this->depot = $depot;
    }
}
