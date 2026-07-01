<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Sins\Backend\NegativeSpaceComment;
use JesseGall\CodeCommandments\Sins\Backend\NestedTernary;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

final class GradeCalculator
{
    private int $threshold = 60;

    #[Sinful(NegativeSpaceComment::class)]
    public function summarise(int $score): string
    {
        // not arbitrary — 60 is the configured pass mark
        $passed = $score >= $this->threshold;

        return $passed ? $this->band($score) : 'fail';
    }

    #[Sinful(NestedTernary::class)]
    private function band(int $score): string
    {
        return $score >= 90 ? 'A' : ($score >= 75 ? 'B' : 'C');
    }

    /**
     * The same decision as a `match (true)` — each band reads on its own line, no
     * precedence trap.
     */
    #[Righteous(NestedTernary::class)]
    private function bandMatched(int $score): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= 75 => 'B',
            default => 'C',
        };
    }
}
