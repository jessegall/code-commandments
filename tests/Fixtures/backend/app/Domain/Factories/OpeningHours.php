<?php

namespace Shop\Domain\Factories;

use JesseGall\CodeCommandments\Sins\Backend\RepeatedStaticFactory;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;

final class OpeningHours
{
    #[Sinful(RepeatedStaticFactory::class)]
    public function status(int $opens, int $closes, int $hour): string
    {
        if (Window::between($opens, $closes)->covers($hour)) {
            return 'open';
        }

        return sprintf('closed, open %d hours a day', Window::between($opens, $closes)->hours());
    }

    #[Fixed(RepeatedStaticFactory::class)]
    public function statusOnce(int $opens, int $closes, int $hour): string
    {
        $window = Window::between($opens, $closes);
        if ($window->covers($hour)) {
            return 'open';
        }

        return sprintf('closed, open %d hours a day', $window->hours());
    }
}
