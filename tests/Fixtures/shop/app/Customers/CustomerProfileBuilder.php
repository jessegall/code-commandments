<?php

namespace Shop\Customers;

use JesseGall\CodeCommandments\Detectors\Backend\NewDataObjectDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Data\CustomerData;
use Shop\Models\Customer;

/**
 * Hand-constructs the data object with `new` instead of `CustomerData::from()`.
 */
final class CustomerProfileBuilder
{
    #[Sinful(NewDataObjectDetector::class)]
    public function build(Customer $customer): CustomerData
    {
        return new CustomerData(
            id: $customer->id,
            name: $customer->name,
            email: $customer->email,
        );
    }
}
