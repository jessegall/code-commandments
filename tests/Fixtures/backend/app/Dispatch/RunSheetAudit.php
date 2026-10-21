<?php

namespace Shop\Dispatch;

use JesseGall\CodeCommandments\Sins\Backend\UnreadCollaborator;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * Two look-alikes that are not the sin. The registry is read, once, in one method; and the
 * depot is handed in only to be checked, used as the argument it is and promoted by nobody,
 * so there is no field here that goes unread.
 */
final class RunSheetAudit
{
    private readonly string $depot;

    #[Righteous(UnreadCollaborator::class)]
    public function __construct(private readonly CarrierRegistry $carriers, string $depot)
    {
        $this->depot = strtoupper($depot);
    }

    public function carrierFor(string $sheet): Carrier
    {
        return $this->carriers->get($sheet);
    }

    public function depot(): string
    {
        return $this->depot;
    }
}
