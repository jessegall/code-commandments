<?php

namespace Shop\Giftwrap\Contracts;

use JesseGall\CodeCommandments\Sins\Backend\LoneImplementerInterface;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * The wrapping paper left, as the gift-wrap desk asks the warehouse for it: a port the warehouse fills.
 */
#[Righteous(LoneImplementerInterface::class)]
interface WrapStock
{
    public function rollsLeft(): int;
}
