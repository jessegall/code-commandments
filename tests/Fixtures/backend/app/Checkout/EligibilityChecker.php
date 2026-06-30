<?php

namespace Shop\Checkout;

use JesseGall\CodeCommandments\Sins\Backend\DeepNesting;

use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Customer;
use Shop\Models\Order;

/**
 * Decides checkout eligibility through a three-deep `if` pyramid — preconditions
 * that should be guard clauses at the top.
 */
final class EligibilityChecker
{
    #[Sinful(DeepNesting::class)]
    public function canCheckout(Customer $customer, Order $order): bool
    {
        if ($customer->active) {
            if ($order->total_cents > 0) {
                if (! $order->on_hold) {
                    return true;
                }
            }
        }

        return false;
    }
}
