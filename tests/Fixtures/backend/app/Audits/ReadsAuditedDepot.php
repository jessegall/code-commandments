<?php

namespace Shop\Audits;

/**
 * Names the depot of the class that uses it, read from that class's own depot.
 */
trait ReadsAuditedDepot
{
    public function depotName(): string
    {
        return $this->depot->name;
    }
}
