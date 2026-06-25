<?php

namespace App\Orders;

class PaymentGatewayFactory
{
    /**
     * @param array<string, mixed> $data
     */
    public function make($data)
    {
        $type = $data['type'] ?? '';

        // classify by the gateway class name
        if (in_array($type, ['Stripe', 'Paypal'])) {
            return $type;
        }

        if ($type == 'Stripe') {
            return 'stripe-handler';
        } elseif ($type == 'Paypal') {
            return 'paypal-handler';
        }

        return null;
    }
}
