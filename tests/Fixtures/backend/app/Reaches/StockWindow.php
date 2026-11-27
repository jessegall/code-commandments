<?php

namespace Shop\Reaches;

use JesseGall\CodeCommandments\Testing\Sinful;

final class StockWindow
{
    /**
     * @return array<string, string|bool>
     */
    #[Sinful(RepeatedReach::class)]
    public static function export(Delivery $delivery): array
    {
        return [
            'day' => $delivery->window->opens->format('Y-m-d'),
            'weekend' => $delivery->window->opens->isWeekend(),
            'until' => $delivery->window->closes->format('H:i'),
        ];
    }
}
