<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\MaskedInvariant;

use JesseGall\CodeCommandments\Testing\Fixed;
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

    #[Sinful(MaskedInvariant::class)]
    public function accepts(string $sku): bool
    {
        return $this->batch?->permits($sku) ?? false;
    }
}

/**
 * The FIX for {@see GradeSelector}: the invariant is made CERTAIN instead of masked — the batch is
 * held non-nullable (a grading pass without one cannot be constructed), so the read is a plain
 * `$this->batch->permits($sku)` with no `?->` and no fake `?? false` answering an impossible state.
 */
#[Fixed(MaskedInvariant::class)]
final class OpenGradeSelector
{
    public function __construct(private readonly ActiveBatch $batch) {}

    /**
     * @param  list<string>  $skus
     * @return list<string>
     */
    public function passing(array $skus): array
    {
        return array_values(array_filter($skus, fn (string $sku) => $this->batch->permits($sku)));
    }

    public function accepts(string $sku): bool
    {
        return $this->batch->permits($sku);
    }
}

final class ActiveBatch
{
    /**
     * @param list<string> $blocked
     */
    public function __construct(private readonly string $code, private readonly array $blocked = []) {}

    public function permits(string $sku): bool
    {
        return $sku !== $this->code && ! in_array($sku, $this->blocked, true);
    }
}
