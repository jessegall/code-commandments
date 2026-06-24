<?php

namespace App\OptionCorpus\OptionJustifiedTransformChain;

use JesseGall\PhpTypes\Option;

/** Second call site: previews a coupon for the UI, and re-uses the SAME Option in two ways. */
final class CouponPreviewService
{
    public function __construct(
        private readonly CouponRepository $coupons,
    ) {}

    /** @return array{valid: bool, label: ?string} */
    public function preview(string $code): array
    {
        $coupon = $this->coupons->findActive($code);

        // Absence is a real domain answer here ("valid: false"), and the same Option
        // is mapped into a label without a second lookup or a repeated null check.
        return [
            'valid' => $coupon->isSome(),
            'label' => $coupon
                ->map(fn (CouponCode $c) => "{$c->value} (-{$c->percentOff}%)")
                ->getOrNull(),
        ];
    }
}
