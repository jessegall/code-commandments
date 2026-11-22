<?php

namespace Shop\Customers;

use Illuminate\Support\Collection;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\ManualHydrationLoop;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Data\CustomerData;
use Shop\Models\Customer;

/**
 * The newest customers, hydrated one by one off a query chain the builder forwards to the query builder.
 */
final class RecentCustomerList
{
    /**
     * @return Collection<int, CustomerData>
     */
    #[Sinful(ManualHydrationLoop::class)]
    public function newest(): Collection
    {
        return Customer::query()
            ->orderBy('created_at')
            ->get()
            ->map(fn (Customer $customer) => CustomerData::from($customer));
    }
}
