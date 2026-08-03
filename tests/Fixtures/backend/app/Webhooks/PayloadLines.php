<?php

namespace Shop\Webhooks;

use JesseGall\CodeCommandments\Sins\Backend\CoalescedLoopSubject;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Replays the parcels an inbound shipment notice carries. The reach is a PROPERTY on the object
 * the caller handed over, not an array key — same buried question, different spelling: whether
 * the notice carries any parcels is a precondition, decided inside the loop header.
 */
final class PayloadLines
{
    private int $replayed = 0;

    #[Sinful(CoalescedLoopSubject::class)]
    public function replay(object $notice): void
    {
        foreach ($notice->parcels ?? [] as $parcel) {
            $this->enqueue($parcel->tracking);

            $this->replayed++;
        }
    }

    public function replayed(): int
    {
        return $this->replayed;
    }

    private function enqueue(string $tracking): void {}
}
