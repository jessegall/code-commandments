<?php

namespace Shop\Customers;

use JesseGall\PhpTypes\Option;
use Shop\Exceptions\CustomerNotFoundException;
use Shop\Models\Customer;

/**
 * Absence done right — the righteous twin for the absence detectors.
 */
final class CustomerDirectory
{
    /** Resolve-or-throw: presence is assumed, a miss is a broken state. */
    public function getByEmail(string $email): Customer
    {
        return Customer::query()->where('email', $email)->first()
            ?? throw CustomerNotFoundException::forEmail($email);
    }

    /**
     * A genuine miss that travels — the absence rides in the type.
     *
     * @return Option<Customer>
     */
    public function search(string $email): Option
    {
        return Option::fromNullable(Customer::query()->where('email', $email)->first());
    }

    /**
     * "Nothing" has an empty form — returns [], never null.
     *
     * @return array<int, Customer>
     */
    public function active(): array
    {
        return Customer::query()->where('active', true)->get()->all();
    }

    /** An honest null: one local caller checks it on the spot. */
    private function latestDraft(): ?Customer
    {
        return Customer::query()->where('status', 'draft')->latest()->first();
    }

    public function hasDraft(): bool
    {
        return $this->latestDraft() !== null;
    }
}
