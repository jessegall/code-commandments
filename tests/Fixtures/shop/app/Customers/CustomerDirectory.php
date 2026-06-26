<?php

namespace Shop\Customers;

use JesseGall\PhpTypes\Option;
use Shop\Exceptions\CustomerNotFoundException;
use Shop\Models\Customer;

/**
 * Absence done right — the righteous twin for the absence detectors. It carries
 * NO #[Sinful] markers on purpose: every "might be missing" here is modelled so
 * that no detector fires. Each method shows a different correct choice:
 *
 *  - getByEmail(): Customer        resolve-or-throw — presence is assumed, a miss
 *                                  is a broken state, so it throws a NAMED exception
 *                                  (not a ?Customer pushed onto every caller).
 *  - search(): Option             a genuine miss that travels — the absence rides
 *                                  in the type, every consumer must open the Option.
 *  - active(): array              "nothing" has an empty form — returns [], never null.
 *  - latestDraft(): ?Customer     an honest null: ONE local caller checks it on the
 *                                  spot (blast radius of one), so a bare nullable is
 *                                  fine — an Option there would be ceremony.
 */
final class CustomerDirectory
{
    public function getByEmail(string $email): Customer
    {
        return Customer::query()->where('email', $email)->first()
            ?? throw CustomerNotFoundException::forEmail($email);
    }

    /**
     * @return Option<Customer>
     */
    public function search(string $email): Option
    {
        return Option::fromNullable(Customer::query()->where('email', $email)->first());
    }

    /**
     * @return array<int, Customer>
     */
    public function active(): array
    {
        return Customer::query()->where('active', true)->get()->all();
    }

    private function latestDraft(): ?Customer
    {
        return Customer::query()->where('status', 'draft')->latest()->first();
    }

    public function hasDraft(): bool
    {
        return $this->latestDraft() !== null;
    }
}
