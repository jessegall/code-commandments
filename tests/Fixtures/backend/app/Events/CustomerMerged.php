<?php

namespace Shop\Events;

/** Two customer records were merged. The merge tool that raised this was removed last quarter. */
final readonly class CustomerMerged
{
    public function __construct(public string $survivorId, public string $mergedId) {}
}
