<?php

namespace Shop\Tills;

use JesseGall\CodeCommandments\Sins\Backend\LoneImplementerInterface;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * A role a sale may play: one the till can take back. It is asked for by a type test, so it marks a role and
 * stands even with a single sale that plays it.
 */
#[Righteous(LoneImplementerInterface::class)]
interface Refundable
{
    public function refundCents(): int;
}
