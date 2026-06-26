<?php

namespace Shop\Pricing;

use JesseGall\CodeCommandments\Detectors\Backend\NearDuplicateFunctionDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

final class TierScorer
{
    /** @var list<int> */
    private array $entries = [];

    public function add(int $weightPoints): void
    {
        $this->entries[] = $weightPoints;
    }

    public function bracket(int $value): string
    {
        return match (true) {
            $value >= 900 => 'platinum',
            $value >= 500 => 'gold',
            $value >= 200 => 'silver',
            default => 'bronze',
        };
    }

    public function label(int $value): string
    {
        return sprintf('tier:%s(%d)', $this->bracket($value), $value);
    }

    #[Sinful(NearDuplicateFunctionDetector::class)]
    public function scoreFrom(int $seed): int
    {
        $score = $seed;

        foreach ($this->entries as $weight) {
            if ($weight > 0) {
                $score += $weight * 2;
            }
        }

        return $score;
    }
}
