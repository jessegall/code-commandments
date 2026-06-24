<?php

namespace App\OptionCorpus\OptionJustifiedTransformChain;

/** Second call site: previews a coupon for the UI, re-coalescing the same nullable twice. */
final class CouponPreviewService
{
    public function __construct(
        private readonly CouponRepository $coupons,
    ) {}

    /** @return array{valid: bool, label: ?string} */
    public function preview(string $code): array
    {
        $coupon = $this->coupons->findActive($code);

        // The same nullable is interrogated twice: once for the boolean, once via ?->.
        // Easy to let the two answers drift out of sync as this grows.
        return [
            'valid' => $coupon !== null,
            'label' => $coupon !== null ? "{$coupon->value} (-{$coupon->percentOff}%)" : null,
        ];
    }
}
