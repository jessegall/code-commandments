<?php

namespace Shop\Integrations;

use JesseGall\CodeCommandments\Sins\Backend\DataClump;

use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Reconciles stock counts against an upstream channel. The same scope trio that
 * the publisher and the auditor also pass around travels through here too.
 */
final class InventoryReconciler
{
    public function __construct(private readonly int $batchSize = 100) {}

    #[Sinful(DataClump::class)]
    public function reconcile(string $shopId, string $userId, string $channelId): bool
    {
        foreach ($this->chunks() as $chunk) {
            if ($chunk > $this->batchSize) {
                return false;
            }
        }

        return $shopId !== '' && $userId !== '' && $channelId !== '';
    }

    /**
     * @return list<int>
     */
    private function chunks(): array
    {
        return range(1, $this->batchSize);
    }
}
