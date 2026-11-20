<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Sins\Backend\BloatedDocblock;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Tracks whether a shipment is ready to leave.
 *
 * The loading screen groups parcels by this stage and repeats the total on the carrier handover sheet.
 */
#[Sinful(BloatedDocblock::class)]
enum DispatchStage
{
    case Ready;
    case HandedOver;
}
