<?php

namespace Shop\Imports;

use JesseGall\CodeCommandments\Sins\Backend\ConcreteCrossModuleDependency;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Vouchers\VoucherAggregate;

/**
 * One imported voucher row and the aggregate it is replayed into: the aggregate is the domain record being
 * written, not a service the import calls.
 */
final class VoucherImportContext
{
    #[Righteous(ConcreteCrossModuleDependency::class)]
    public function __construct(
        public readonly string $code,
        public VoucherAggregate $aggregate,
    ) {}
}
