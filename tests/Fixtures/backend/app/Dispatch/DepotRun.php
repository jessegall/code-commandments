<?php

namespace Shop\Dispatch;

use JesseGall\CodeCommandments\Sins\Backend\TrainWreck;
use JesseGall\CodeCommandments\Testing\Fixed;

/**
 * One run out of one depot. It answers for the town it serves itself, which is what keeps the
 * board from walking through the depot to find out.
 */
final readonly class DepotRun
{
    public function __construct(private Depot $depot, public string $reference) {}

    public function depot(): Depot
    {
        return $this->depot;
    }

    #[Fixed(TrainWreck::class)]
    public function town(): string
    {
        return $this->depot->town();
    }
}
