<?php

namespace Shop\Legacy;

use JesseGall\CodeCommandments\Detectors\Backend\ConstClassEnumDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Payment states as loose string constants — a closed set that should be a backed enum.
 */
#[Sinful(ConstClassEnumDetector::class)]
final class PaymentStatuses
{
    /** Authorisation requested, awaiting the gateway. */
    const PENDING = 'pending';

    /** Funds held but not yet taken. */
    const AUTHORISED = 'authorised';

    /** Money moved; the order can ship. */
    const CAPTURED = 'captured';

    /** Reversed after capture. */
    const REFUNDED = 'refunded';
}
