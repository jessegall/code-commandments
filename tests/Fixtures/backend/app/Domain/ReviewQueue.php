<?php

namespace Shop\Domain;

use JesseGall\CodeCommandments\Sins\Backend\RepeatedGuard;
use JesseGall\CodeCommandments\Testing\Sinful;

/*
 * CROSS-CLASS partner of `PublishGate`: the exact `$item->published && $item->approved` guard, copied into a
 * different class in a different file. Proves the fingerprint buckets across the whole codebase, not just
 * within one class. The fix is one shared named predicate the two callers both use.
 */
final class ReviewQueue
{
    /**
     * @param  list<mixed>  $items
     *
     * @return list<mixed>
     */
    #[Sinful(RepeatedGuard::class)]
    public function promote(array $items): array
    {
        $ready = [];

        foreach ($items as $item) {
            if ($item->published && $item->approved) {
                $ready[] = $item;
            }
        }

        return $ready;
    }

    public function pending(int $total, int $done): int
    {
        return max($total - $done, 0);
    }
}
