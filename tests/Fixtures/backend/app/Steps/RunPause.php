<?php

namespace Shop\Steps;

use JesseGall\CodeCommandments\Sins\Backend\ImpossibleGuard;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * The pause between two steps of a run, from the pace its settings chose.
 */
final class RunPause
{
    public function __construct(private readonly RunSettings $settings) {}

    #[Righteous(ImpossibleGuard::class)]
    public function seconds(): int
    {
        $pace = $this->settings->choice('pace', StepPace::class);
        if ($pace === null) {
            return 0;
        }

        return $pace->pauseSeconds();
    }
}
