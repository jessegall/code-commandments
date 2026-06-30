<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Detectors\Backend\MaskedInvariantDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Opens a batch in one call, then reads it back through `?->… ?? false` — the
 * field is set for the whole grading pass, so the default answers a state that
 * can't happen.
 */
final class GradeSelector
{
    private ?ActiveBatch $batch = null;

    public function open(string $code): void
    {
        $this->batch = new ActiveBatch($code);
    }

    /**
     * @param  list<string>  $skus
     * @return list<string>
     */
    public function passing(array $skus): array
    {
        $kept = [];

        foreach ($skus as $sku) {
            if ($this->accepts($sku)) {
                $kept[] = $sku;
            }
        }

        return $kept;
    }

    #[Sinful(MaskedInvariantDetector::class)]
    public function accepts(string $sku): bool
    {
        return $this->batch?->permits($sku) ?? false;
    }
}

final class ActiveBatch
{
    /** @param list<string> $blocked */
    public function __construct(private readonly string $code, private readonly array $blocked = []) {}

    public function permits(string $sku): bool
    {
        return $sku !== $this->code && ! in_array($sku, $this->blocked, true);
    }
}
