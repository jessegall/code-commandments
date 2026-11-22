<?php

namespace Shop\Vouchers;

use Spatie\EventSourcing\AggregateRoots\AggregateRoot;

/**
 * A voucher's history as stored events: issued, redeemed, voided.
 */
final class VoucherAggregate extends AggregateRoot
{
    public int $redeemedCents = 0;
}
