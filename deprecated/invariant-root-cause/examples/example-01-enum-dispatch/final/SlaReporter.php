<?php

declare(strict_types=1);

namespace Shop\Fulfilment;

final class SlaReporter
{
    /**
     * @param list<OrderStatus> $statuses
     */
    public function urgentCount(array $statuses): int
    {
        $count = 0;

        foreach ($statuses as $status) {
            // slaPriority() is total now — no compensation needed.
            if ($status->slaPriority() >= 3) {
                $count++;
            }
        }

        return $count;
    }
}
