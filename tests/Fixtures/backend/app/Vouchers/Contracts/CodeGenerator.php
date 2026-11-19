<?php

namespace Shop\Vouchers\Contracts;

use JesseGall\CodeCommandments\Sins\Backend\LoneImplementerInterface;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Makes the code printed on a new voucher.
 */
#[Sinful(LoneImplementerInterface::class)]
interface CodeGenerator
{
    public const MAXIMUM_ATTEMPTS_BEFORE_GIVING_UP = 25;

    public function next(): string;

    public function isAlreadyPrintedOnAnotherVoucher(string $candidateCode): bool;
}
