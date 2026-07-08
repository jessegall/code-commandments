<?php

namespace Shop\Domain;

use JesseGall\CodeCommandments\Sins\Backend\RepeatedGuard;
use JesseGall\CodeCommandments\Testing\Righteous;

/*
 * Righteous twin for RepeatedGuard: a substantive compound guard used exactly ONCE. A one-off condition is
 * fine — only a guard copied verbatim into ≥2 places wants a name. Must NOT flag.
 */
final class OneOffGate
{
    #[Righteous(RepeatedGuard::class)]
    public function shippable($order, $address): bool
    {
        return $order->paid && $address->confirmed;
    }
}
