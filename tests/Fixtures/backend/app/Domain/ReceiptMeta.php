<?php

namespace Shop\Domain;

use JesseGall\CodeCommandments\Sins\Backend\ConditionalArraySpread;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/*
 * Assembles receipt metadata, splicing a discount block only when a coupon applies — the empty-first
 * `...($coupon === null ? [] : [...])` order. A third distinct shape (nested block, not a scalar key).
 */
final class ReceiptMeta
{
    public function __construct(
        private readonly string $number,
        private readonly ?string $coupon = null,
    ) {}

    /**
     * @return array{number: string, discount?: array{coupon: string, applied: bool}}
     */
    #[Sinful(ConditionalArraySpread::class)]
    public function lines(): array
    {
        return [
            'number' => $this->number,
            ...($this->coupon === null ? [] : ['discount' => ['coupon' => $this->coupon, 'applied' => true]]),
        ];
    }

    /*
     * Righteous — the coupon is resolved to a value first (a null-object default), then set unconditionally.
     * No conditional spread. Must NOT flag.
     */
    /**
     * @return array{number: string, coupon: string}
     */
    #[Righteous(ConditionalArraySpread::class)]
    public function summary(): array
    {
        return [
            'number' => $this->number,
            'coupon' => $this->coupon ?? 'none',
        ];
    }
}
