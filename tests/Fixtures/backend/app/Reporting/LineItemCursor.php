<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\ManualHydrationLoop;

use Iterator;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Data\OrderLineData;

/**
 * Drains a cursor into OrderLineData with `::from()` in a while-loop, one row at
 * a time.
 */
final class LineItemCursor
{
    /**
     * @return array<int, OrderLineData>
     */
    #[Sinful(ManualHydrationLoop::class)]
    public function drain(Iterator $cursor): array
    {
        $lines = [];

        while ($cursor->valid()) {
            $lines[] = OrderLineData::from($cursor->current());
            $cursor->next();
        }

        return $lines;
    }
}
