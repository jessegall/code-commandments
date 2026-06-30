<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Sins\Backend\LoopInvertedGuard;

use JesseGall\CodeCommandments\Testing\Righteous;
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
    #[Sinful(LoopInvertedGuard::class)]
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

/**
 * The same pass with the condition inverted into a `continue` guard — the body stays
 * flat at the loop's top level.
 */
final class GuardedRowProcessor
{
    /**
     * @param  array<int, object>  $rows
     */
    #[Righteous(LoopInvertedGuard::class)]
    public function process(array $rows): void
    {
        foreach ($rows as $row) {
            if ($row->total <= 0) {
                continue;
            }

            $this->normalise($row);
            $this->persist($row);
        }
    }

    private function normalise(object $row): void {}

    private function persist(object $row): void {}
}
