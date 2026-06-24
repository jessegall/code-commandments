<?php

namespace App\OptionCorpus\OptionJustifiedRepositoryBranch\Messy;

/** Call site #1: the create-or-update branch is easy to forget — nothing forces it. */
final readonly class RegisterOrderService
{
    public function __construct(
        private CustomerRepository $customers,
    ) {}

    public function placeOrder(string $email, string $name, int $amountCents): Order
    {
        $customer = $this->customers->findByEmail($email);

        // The branch is hand-rolled and trivially skippable. A careless edit
        // (e.g. `$customer->id` straight after the call) compiles fine and blows
        // up only at runtime when the customer doesn't exist — the exact
        // "used a null by accident" bug.
        if ($customer === null) {
            $customer = $this->provisionCustomer($email, $name);
        }

        return new Order($customer->id, $amountCents, $customer->defaultCurrency);
    }

    private function provisionCustomer(string $email, string $name): Customer
    {
        return new Customer("cust_new_{$email}", $email, $name, 'USD');
    }
}
