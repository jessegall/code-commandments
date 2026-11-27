<?php

namespace Shop\Absences;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The scans of one shelf, newest last, and what the newest one says.
 */
final class ShelfScanLog
{
    /**
     * @param  list<ShelfScan>  $scans
     */
    public function __construct(private readonly array $scans) {}

    #[Sinful(MadeNullThenChecked::class)]
    public function newestCount(): int
    {
        $newest = $this->scans === [] ? null : $this->scans[array_key_last($this->scans)];

        if ($newest === null) {
            return 0;
        }

        return $newest->count;
    }

    #[Righteous(MadeNullThenChecked::class)]
    public function summary(): ShelfSummary
    {
        $newest = $this->scans === [] ? null : $this->scans[array_key_last($this->scans)];

        return new ShelfSummary(count($this->scans), $newest);
    }
}
