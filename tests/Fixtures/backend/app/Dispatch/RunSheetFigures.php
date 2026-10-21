<?php

namespace Shop\Dispatch;

/**
 * The figures of one run sheet as the depot fills them in: how many parcels went on it, what
 * they weighed, and whether the sheet has been sealed for the night.
 */
final class RunSheetFigures
{
    public int $grams = 0;

    public bool $sealed = false;

    public function __construct(public int $parcels = 0) {}

    public function isWeighed(): bool
    {
        return $this->grams > 0;
    }
}
