<?php

declare(strict_types=1);

namespace App\Clean;

/**
 * Fixture for the enum-case docs SIN: every case carries a descriptive comment
 * (mixing docblock and line styles), so the prophet must stay silent.
 */
enum DocumentedStatus: string
{
    /** Payment captured; the order is awaiting fulfilment. */
    case Paid = 'paid';

    // Handed to the carrier — a tracking number now exists.
    case Shipped = 'shipped';

    /* Voided before shipment; stock released and the buyer refunded. */
    case Cancelled = 'cancelled';
}
