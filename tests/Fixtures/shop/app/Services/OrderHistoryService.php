<?php

namespace Shop\Services;

use JesseGall\CodeCommandments\Detectors\Backend\NullableCollectionReturnDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Customer;
use Shop\Models\Order;
use Shop\Repositories\OrderRepository;
use Shop\ValueObjects\Money;

/**
 * Reads a customer's order history.
 */
final class OrderHistoryService
{
    public function __construct(private readonly OrderRepository $orders) {}

    /**
     * @return array<int, int>|null
     */
    #[Sinful(NullableCollectionReturnDetector::class)]
    public function recentOrderIds(Customer $customer): ?array
    {
        $ids = $this->orders->recentIdsFor($customer->id);

        return $ids ?: null;
    }

    /**
     * @return array<int, Order>
     */
    public function forCustomer(Customer $customer): array
    {
        return $this->orders->paidForCustomer($customer->id);
    }

    public function totalSpent(Customer $customer): Money
    {
        $total = Money::ofCents(0);

        foreach ($this->forCustomer($customer) as $order) {
            $total = $total->add($order->total());
        }

        return $total;
    }
}
