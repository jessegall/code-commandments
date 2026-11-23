<?php

namespace Shop\Audits;

/**
 * The depot an audited till stands in.
 */
final readonly class AuditedDepot
{
    public function __construct(public string $name) {}
}
