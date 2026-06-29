<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Detectors\Backend\LoopInvertedGuardDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Processes report rows — the whole loop body buried under an `if` instead of a
 * `continue` guard.
 */
final class RowProcessor
{
    /**
     * @param  array<int, object>  $rows
     */
    #[Sinful(LoopInvertedGuardDetector::class)]
    public function process(array $rows): void
    {
        foreach ($rows as $row) {
            if ($row->total > 0) {
                $this->normalise($row);
                $this->persist($row);
            }
        }
    }

    private function normalise(object $row): void {}

    private function persist(object $row): void {}
}
