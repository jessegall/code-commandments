<?php

namespace Shop\Enums;

use JesseGall\CodeCommandments\Sins\Backend\EnumCaseOrChain;
use JesseGall\CodeCommandments\Testing\Fixed;

enum PaymentMethod: string
{
    case Card = 'card';
    case Ideal = 'ideal';
    case PayPal = 'paypal';

    /**
     * The eligible set, sealed where the cases are. The call site used to re-derive it from an
     * or-chain, which is a copy of this answer that no new case can update.
     */
    #[Fixed(EnumCaseOrChain::class)]
    public function isInstant(): bool
    {
        return match ($this) {
            self::Card, self::Ideal => true,
            self::PayPal => false,
        };
    }
}
