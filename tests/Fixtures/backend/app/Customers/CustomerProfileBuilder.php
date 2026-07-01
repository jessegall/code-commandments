<?php

namespace Shop\Customers;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\NewDataObject;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Data\CustomerData;
use Shop\Models\Customer;

/**
 * Hand-constructs the data object with `new` instead of `CustomerData::from()`.
 */
final class CustomerProfileBuilder
{
    #[Sinful(NewDataObject::class)]
    public function build(Customer $customer): CustomerData
    {
        return new CustomerData(
            id: $customer->id,
            name: $customer->name,
            email: $customer->email,
        );
    }

    #[Righteous(NewDataObject::class)]
    public function buildFrom(Customer $customer): CustomerData
    {
        return CustomerData::from($customer);
    }
}
