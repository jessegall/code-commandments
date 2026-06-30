<?php

namespace Shop\Legacy;

use JesseGall\CodeCommandments\Sins\Backend\ConstClassEnum;

use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Payment states as loose string constants — a closed set that should be a backed enum.
 */
#[Sinful(ConstClassEnum::class)]
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
