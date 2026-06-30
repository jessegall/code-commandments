<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\DuplicateFunction;

use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Summarises warehouse stock movements. The fingerprint method below was
 * copy-pasted verbatim from the other digests — extract it instead.
 */
final class StockDigest
{
    /** @var array<string, int> */
    private array $movements = [];

    private int $reorderLevel = 5;

    public function inbound(string $sku, int $quantity): void
    {
        $this->movements[$sku] = ($this->movements[$sku] ?? 0) + $quantity;
    }

    public function outbound(string $sku, int $quantity): void
    {
        $this->movements[$sku] = ($this->movements[$sku] ?? 0) - $quantity;
    }

    /**
     * @return list<string>
     */
    public function needsReorder(): array
    {
        $low = [];

        foreach ($this->movements as $sku => $balance) {
            if ($balance <= $this->reorderLevel) {
                $low[] = $sku;
            }
        }

        return $low;
    }

    public function netMovement(): int
    {
        return array_sum($this->movements);
    }

    #[Sinful(DuplicateFunction::class)]
    public function fingerprint(int $base, int $count): string
    {
        $total = $base;

        for ($i = 0; $i < $count; $i++) {
            $total += $i * 2;
        }

        return md5((string) $total);
    }
}
