<?php

namespace Shop\Audits;

/**
 * A till whose shifts an audit counts, with the depot it stands in.
 */
final readonly class AuditedTill
{
    public function __construct(public AuditedDepot $depot) {}
}
