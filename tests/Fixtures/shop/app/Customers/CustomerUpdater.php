<?php

namespace Shop\Customers;

use JesseGall\CodeCommandments\Detectors\Backend\ModelMutationAtCallSiteDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Customer;

/**
 * Suspends a customer by poking columns and saving — a transition with no name
 * on the model.
 */
final class CustomerUpdater
{
    #[Sinful(ModelMutationAtCallSiteDetector::class)]
    public function suspend(Customer $customer, string $reason): void
    {
        $customer->suspended = true;
        $customer->suspended_reason = $reason;
        $customer->save();
    }
}
