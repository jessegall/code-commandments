<?php

namespace Shop\Domain;

use JesseGall\CodeCommandments\Sins\Backend\CoupledFields;
use JesseGall\CodeCommandments\Testing\Righteous;

final class Shopper
{
    public function __construct(public readonly string $name) {}
}

final class Voucher
{
    public function __construct(public readonly string $code) {}
}

/*
 * Righteous twin for CoupledFields: a genuine aggregate. It HOLDS a shopper and a voucher and reaches
 * `voucher->code` (a string) beside `$this->shopper` (a Shopper) — DIFFERENT types, a context of related-but-
 * distinct parts, not two peers of one concept. And no field mirrors another's data. Must NOT flag.
 */
#[Righteous(CoupledFields::class)]
final class OrderContext
{
    public function __construct(
        public readonly Shopper $shopper,
        public readonly Voucher $voucher,
    ) {}

    public function heading(): array
    {
        return [$this->shopper, $this->voucher->code];
    }

    public function footer(): array
    {
        return [$this->shopper, $this->voucher->code];
    }
}
