<?php

namespace Shop\Customers;

use JesseGall\CodeCommandments\Detectors\Backend\ManualHydrationLoopDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Contracts\Mailer;
use Shop\Data\CustomerData;

/**
 * Hydrates a batch of customers with `::from()` indexed in a for-loop instead of
 * a single `::collect()`, then welcomes each one.
 */
final class CustomerBatchImporter
{
    public function __construct(private readonly Mailer $mailer) {}

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, CustomerData>
     */
    #[Sinful(ManualHydrationLoopDetector::class)]
    public function importBatch(array $rows): array
    {
        $customers = [];
        $total = count($rows);

        for ($i = 0; $i < $total; $i++) {
            $customer = CustomerData::from($rows[$i]);
            $this->mailer->send($customer->email, 'Welcome', 'Thanks for joining.');
            $customers[$i] = $customer;
        }

        return $customers;
    }
}
