<?php

namespace Shop\Dispatch;

use JesseGall\CodeCommandments\Sins\Backend\CoalescedLoopSubject;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

final class ShipmentManifest
{
    public function __construct(private readonly array $queued) {}

    #[Sinful(CoalescedLoopSubject::class)]
    public function incomingWeight(array $manifest): int
    {
        $weight = 0;

        foreach ($manifest as $shipment) {
            foreach ($shipment['pieces'] ?? $shipment['parcels'] ?? [] as $piece) {
                $weight += $piece;
            }
        }

        return $weight;
    }

    #[Righteous(CoalescedLoopSubject::class)]
    public function queuedWeight(): int
    {
        $weight = 0;
        $queued = $this->queued;

        foreach ($queued as $shipment) {
            foreach ($shipment['pieces'] as $piece) {
                $weight += $piece;
            }
        }

        return $weight;
    }
}
