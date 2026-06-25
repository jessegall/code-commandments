<?php

namespace Shop\Repositories;

use Shop\Exceptions\CustomerNotFoundException;
use Shop\Models\Customer;
use Shop\ValueObjects\Email;

/**
 * Reads customers through query methods.
 */
final class CustomerRepository
{
    public function byEmailOrFail(Email $email): Customer
    {
        return Customer::query()->where('email', $email->value)->first()
            ?? throw CustomerNotFoundException::forEmail($email->value);
    }
}
