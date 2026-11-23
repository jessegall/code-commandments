<?php

namespace Shop\Audits;

use JesseGall\CodeCommandments\Sins\Backend\InlineDocblock;
use JesseGall\CodeCommandments\Sins\Backend\TrainWreck;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Names the depots a batch of counted shifts ran in, the shifts typed only by the comment above the loop.
 */
final class ShiftAudit
{
    /**
     * @param  list<mixed>  $shifts
     * @return list<string>
     */
    #[Sinful(TrainWreck::class)]
    #[Sinful(InlineDocblock::class)]
    public function depotNames(array $shifts): array
    {
        $names = [];
        /** @var AuditedShift $shift */
        foreach ($shifts as $shift) {
            $names[] = $shift->till->depot->name;
        }

        return $names;
    }
}
