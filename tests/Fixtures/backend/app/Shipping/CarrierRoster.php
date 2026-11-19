<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Sins\Backend\HandRolledRegistry;
use JesseGall\CodeCommandments\Testing\Fixed;
use Shop\Support\Registry;

/**
 * The carriers the shop ships with, kept under their carrier codes by the shared Registry.
 */
#[Fixed(HandRolledRegistry::class)]
final class CarrierRoster extends Registry
{
}
