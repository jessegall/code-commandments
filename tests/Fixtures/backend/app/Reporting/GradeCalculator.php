<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Detectors\Backend\NestedTernaryDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

final class GradeCalculator
{
    private int $threshold = 60;

    public function summarise(int $score): string
    {
        $passed = $score >= $this->threshold;

        return $passed ? $this->band($score) : 'fail';
    }

    #[Sinful(NestedTernaryDetector::class)]
    private function band(int $score): string
    {
        return $score >= 90 ? 'A' : ($score >= 75 ? 'B' : 'C');
    }
}
