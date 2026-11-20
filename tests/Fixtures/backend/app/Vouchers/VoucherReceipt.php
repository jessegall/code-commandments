<?php

namespace Shop\Vouchers;

use JesseGall\CodeCommandments\Sins\Backend\UndeclaredMember;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

class VoucherTerms
{
    public function __construct(public readonly int $amount)
    {
    }
}

final class VoucherPrintFields
{
    public function __get(string $field): string
    {
        return strtoupper($field);
    }
}

final class VoucherReceipt
{
    #[Sinful(UndeclaredMember::class)]
    public function applies(VoucherTerms $terms, int $subtotal): bool
    {
        return $subtotal >= $terms->minimumSpend && $terms->amount > 0;
    }

    #[Righteous(UndeclaredMember::class)]
    public function heading(VoucherPrintFields $fields): string
    {
        return $fields->heading;
    }
}
