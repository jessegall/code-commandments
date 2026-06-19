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
            // DOWNSTREAM SYMPTOM: the caller compensates for the nullable with
            // `?? 0`, so the forgotten `Cancelled` case silently scores 0 and is
            // never counted as urgent. Dead once slaPriority() is made total.
            $priority = $status->slaPriority() ?? 0;

            if ($priority >= 3) {
                $count++;
            }
        }

        return $count;
    }
}
