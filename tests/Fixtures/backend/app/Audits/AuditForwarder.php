<?php

namespace Shop\Audits;

use JesseGall\CodeCommandments\Sins\Backend\UnreadCollaborator;
use JesseGall\CodeCommandments\Sins\Backend\UnusedPrivateMember;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Hands every call it does not declare to the shift audit; the depot it keeps is read by nothing.
 */
final class AuditForwarder
{
    #[Sinful(UnusedPrivateMember::class)]
    #[Sinful(UnreadCollaborator::class)]
    private ?AuditedDepot $lastDepot = null;

    public function __construct(private readonly ShiftAudit $audit) {}

    /**
     * @param  list<mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->audit->{$method}(...$arguments);
    }
}
