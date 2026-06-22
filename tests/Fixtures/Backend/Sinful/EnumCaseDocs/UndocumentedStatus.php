<?php

declare(strict_types=1);

namespace App\Sinful;

/**
 * Fixture for the enum-case docs SIN: every case here is undocumented (or only
 * carries a non-descriptive separator), so each must be flagged.
 */
enum UndocumentedStatus: string
{
    case Paid = 'paid';

    // ----------------
    case Shipped = 'shipped';

    /** */
    case Cancelled = 'cancelled';
}
