<?php

namespace Shop\Fulfillment;

/**
 * Picks the window a consignment goes out in.
 */
final class DispatchWindow
{
    /**
     * Two flags, and both are answers the consignment gave — so the caller has already decided
     * half of this before calling it. The pair travels together because it is one object.
     */
    public static function pick(bool $express, bool $weekend): string
    {
        if ($express && $weekend) {
            return 'saturday-am';
        }

        return $express ? 'next-day' : 'standard';
    }
}
