<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Sins\Backend\SideTableTrait;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Which services need a signature is kept as a list of the cases it is true of, beside the cases
 * themselves. A service added tomorrow is silently not one of them, and has nowhere to say it is.
 */
#[Sinful(SideTableTrait::class)]
enum CarrierService: string
{
    case Standard = 'standard';
    case Express = 'express';
    case Insured = 'insured';
    case Pallet = 'pallet';

    private const SIGNED_FOR = [self::Insured, self::Pallet];

    public function needsSignature(): bool
    {
        return in_array($this, self::SIGNED_FOR, true);
    }
}
