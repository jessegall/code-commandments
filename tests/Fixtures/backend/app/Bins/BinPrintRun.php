<?php

namespace Shop\Bins;

/**
 * Labels a whole aisle of bins in one go.
 */
final class BinPrintRun
{
    public function __construct(private readonly BinLabeller $labeller) {}

    public function aisle(string $aisle, int $bins): string
    {
        $labels = '';
        foreach (range(1, $bins) as $bin) {
            $labels .= $this->labeller->label("{$aisle}-{$bin}");
        }

        return $labels;
    }
}
