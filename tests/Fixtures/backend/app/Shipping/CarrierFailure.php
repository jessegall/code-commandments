<?php

namespace Shop\Shipping;

use RuntimeException;

/**
 * The root of everything a carrier's API can refuse: a bad address, a lapsed contract, a limit reached.
 */
abstract class CarrierFailure extends RuntimeException {}
