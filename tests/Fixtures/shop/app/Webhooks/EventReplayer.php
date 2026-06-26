<?php

namespace Shop\Webhooks;

use JesseGall\CodeCommandments\Detectors\Backend\LoopInvertedGuardDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Replays buffered webhook events through a cursor — a while-loop whose body is
 * wholly nested under a freshness check.
 */
final class EventReplayer
{
    #[Sinful(LoopInvertedGuardDetector::class)]
    public function replay(\Iterator $events): int
    {
        $replayed = 0;

        while ($events->valid()) {
            if (! $events->current()->expired) {
                $this->dispatch($events->current());
                $replayed++;
            }
        }

        return $replayed;
    }

    private function dispatch(object $event): void {}
}
