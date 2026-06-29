<?php

namespace Shop\Data;

use Shop\Models\Customer;
use Spatie\LaravelData\Data;

/**
 * Righteous twin for DataMethodHintCollision: the `@method` tag documents the magic
 * `::from(Customer)` overload — the INVISIBLE thing — not the concrete `fromCustomer`
 * factory. No real method named `from` is declared here, so there is no collision.
 *
 * @method static static from(Customer $customer)
 */
final class MembershipData extends Data
{
    public function __construct(
        public readonly int $customerId,
        public readonly string $tier,
    ) {}

    public static function fromCustomer(Customer $customer): self
    {
        return self::from(['customerId' => $customer->id, 'tier' => 'silver']);
    }
}
