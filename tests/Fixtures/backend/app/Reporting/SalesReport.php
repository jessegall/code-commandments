<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Sins\Backend\ArrayReturnBag;
use JesseGall\CodeCommandments\Sins\Backend\ConfigRead;

use JesseGall\CodeCommandments\Testing\Righteous;
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
    #[Sinful(ConfigRead::class)]
    #[Sinful(ArrayReturnBag::class)]
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
    #[Sinful(ConfigRead::class)]
    #[Sinful(ArrayReturnBag::class)]
    public function summary(): array
    {
        $window = config('shop.report.window');

        return [
            'orders' => $this->orders->countSince($window),
            'revenue' => $this->orders->revenueSince($window),
        ];
    }

    /**
     * The same daily figures as a typed report value object — named fields, not a
     * loose string-keyed bag.
     */
    #[Righteous(ArrayReturnBag::class)]
    public function dailyReport(int $day): DailyReport
    {
        $gross = $this->orders->grossForDay($day);

        return new DailyReport(
            gross: $gross,
            net: (int) round($gross * 0.79),
        );
    }

    /**
     * "Nothing" is the empty list, not null — callers iterate without guarding.
     *
     * @return list<int>
     */
    public function bestProductIds(int $limit): array
    {
        return $this->orders->topProductIds($limit);
    }
}
