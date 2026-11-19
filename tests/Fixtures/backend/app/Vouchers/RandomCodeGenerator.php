<?php

namespace Shop\Vouchers;

use Shop\Vouchers\Contracts\CodeGenerator;

/**
 * Voucher codes of eight random upper-case letters and digits.
 */
final class RandomCodeGenerator implements CodeGenerator
{
    public function next(): string
    {
        return strtoupper(bin2hex(random_bytes(4)));
    }

    public function isAlreadyPrintedOnAnotherVoucher(string $candidateCode): bool
    {
        return str_starts_with($candidateCode, '0000');
    }
}
