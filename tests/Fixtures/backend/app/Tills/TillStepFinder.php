<?php

namespace Shop\Tills;

use JesseGall\CodeCommandments\Sins\Backend\ListMirrorsFolder;
use JesseGall\CodeCommandments\Testing\Fixed;

/**
 * Hands the till every step its Steps folder declares, so a new step needs no second edit.
 */
#[Fixed(ListMirrorsFolder::class)]
final class TillStepFinder
{
    public function wire(TillSteps $steps): void
    {
        $steps->addAll(array_map(
            fn (string $file) => __NAMESPACE__.'\\Steps\\'.basename($file, '.php'),
            glob(__DIR__.'/Steps/*.php'),
        ));
    }
}
