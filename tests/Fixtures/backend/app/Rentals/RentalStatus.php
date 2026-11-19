<?php

namespace Shop\Rentals;

/**
 * Where a rental of shop equipment stands.
 */
enum RentalStatus: string
{
    case Out = 'out';
    case Returned = 'returned';
    case Lost = 'lost';
}
