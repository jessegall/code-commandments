<?php

namespace App\Subscriptions;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates and types the inbound "start a subscription" payload.
 */
final class SubscriptionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'string'],
            'plan' => ['required', 'string'],
            'cycle' => ['required', 'string'],
        ];
    }

    public function customerId(): string
    {
        return (string) $this->string('customer_id');
    }

    public function plan(): Plan
    {
        return $this->enum('plan', Plan::class);
    }

    public function cycle(): BillingCycle
    {
        return $this->enum('cycle', BillingCycle::class);
    }
}
