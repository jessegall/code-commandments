<?php

namespace Shop\Data;

use JesseGall\CodeCommandments\Sins\Backend\BlankStringOnTheWire;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;

/**
 * The voucher panel of the checkout page. A voucher that was never entered travels as a blank code,
 * which the page then has to decode back into "no voucher".
 *
 * @method static self from(mixed ...$payload)
 */
#[Sinful(BlankStringOnTheWire::class)]
final class VoucherPanelData extends Data
{
    /**
     * @param  array<int, string>  $rejectedCodes  every code the till turned away this basket
     */
    public function __construct(
        public readonly string $appliedCode,
        public readonly string $discountLabel,
        public readonly array $rejectedCodes = [],
    ) {}

    public function rejections(): int
    {
        return count($this->rejectedCodes);
    }

    public function turnedAway(string $code): bool
    {
        return in_array($code, $this->rejectedCodes, true);
    }
}
