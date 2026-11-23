<?php

namespace Shop\Audits;

/**
 * One counted shift, on the till it ran.
 */
final readonly class AuditedShift
{
    public function __construct(public AuditedTill $till) {}
}
