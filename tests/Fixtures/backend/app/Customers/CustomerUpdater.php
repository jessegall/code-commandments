<?php

namespace Shop\Customers;

use JesseGall\CodeCommandments\Sins\Backend\FeatureEnvy;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\ModelMutationAtCallSite;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Customer;

/**
 * Suspends a customer by poking columns and saving — a transition with no name
 * on the model.
 */
final class CustomerUpdater
{
    #[Sinful(ModelMutationAtCallSite::class)]
    #[Sinful(FeatureEnvy::class)]
    public function suspend(Customer $customer, string $reason): void
    {
        $customer->suspended = true;
        $customer->suspended_reason = $reason;
        $customer->save();
    }

    #[Righteous(ModelMutationAtCallSite::class)]
    public function suspendNamed(Customer $customer, string $reason): void
    {
        $customer->suspend($reason);
    }

    #[Righteous(FeatureEnvy::class)]
    public function suspendByTelling(Customer $customer, string $reason): void
    {
        $customer->suspend($reason);
    }
}
