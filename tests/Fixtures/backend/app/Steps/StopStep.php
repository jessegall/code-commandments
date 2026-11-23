<?php

namespace Shop\Steps;

use JesseGall\CodeCommandments\Sins\Backend\RefusedBequest;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * The step that ends a run: it answers by stopping it, which the runner reads as the run's end.
 */
final class StopStep implements RunStep
{
    #[Righteous(RefusedBequest::class)]
    public function handle(): string
    {
        throw StopRun::here();
    }
}
