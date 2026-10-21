<?php

namespace Shop\Dispatch;

use JesseGall\CodeCommandments\Sins\Backend\TrainWreck;
use JesseGall\CodeCommandments\Testing\Fixed;

/**
 * A depot, which knows where it is and answers for its own town rather than handing out its
 * address for someone else to read.
 */
final readonly class Depot
{
    public function __construct(private DepotAddress $address, public string $code) {}

    public function address(): DepotAddress
    {
        return $this->address;
    }

    #[Fixed(TrainWreck::class)]
    public function town(): string
    {
        return $this->address->town();
    }
}
