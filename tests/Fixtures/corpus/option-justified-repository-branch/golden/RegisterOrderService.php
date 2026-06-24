<?php

namespace App\OptionCorpus\OptionJustifiedRepositoryBranch\Golden;

/** Call site #1: "not found" is a real create-or-update domain decision. */
final readonly class RegisterOrderService
{
    public function __construct(
        private CustomerRepository $customers,
    ) {}

    public function placeOrder(string $email, string $name, int $amountCents): Order
    {
        // Absence drives a genuine branch: provision a brand-new customer vs.
        // reuse the existing one. Option forces both arms to be written.
        $customer = $this->customers->findByEmail($email)->getOrElse(
            fn () => $this->provisionCustomer($email, $name),
        );

        return new Order($customer->id, $amountCents, $customer->defaultCurrency);
    }

    private function provisionCustomer(string $email, string $name): Customer
    {
        return new Customer("cust_new_{$email}", $email, $name, 'USD');
    }
}
