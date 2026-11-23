<?php

namespace Shop\Steps;

/**
 * The step that opens a run by greeting the customer it runs for.
 */
final class GreetStep implements RunStep
{
    public function __construct(private readonly string $customer) {}

    public function handle(): string
    {
        return 'Welcome, ' . $this->customer;
    }
}
