<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Detectors\Backend\ArrayReturnBagDetector;
use JesseGall\CodeCommandments\Detectors\Backend\ConfigReadDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Repositories\OrderRepository;

/**
 * Messy analytics — reads config in the body and hands back loose string-keyed
 * bags instead of typed report value objects.
 */
final class SalesReport
{
    public function __construct(private readonly OrderRepository $orders) {}

    /**
     * @return array<string, int|string>
     */
    #[Sinful(ConfigReadDetector::class)]
    #[Sinful(ArrayReturnBagDetector::class)]
    public function daily(int $day): array
    {
        $currency = config('shop.currency');
        $gross = $this->orders->grossForDay($day);

        return [
            'currency' => $currency,
            'gross' => $gross,
            'net' => (int) round($gross * 0.79),
        ];
    }

    /**
     * @return array<string, int>
     */
    #[Sinful(ConfigReadDetector::class)]
    #[Sinful(ArrayReturnBagDetector::class)]
    public function summary(): array
    {
        $window = config('shop.report.window');

        return [
            'orders' => $this->orders->countSince($window),
            'revenue' => $this->orders->revenueSince($window),
        ];
    }
}
