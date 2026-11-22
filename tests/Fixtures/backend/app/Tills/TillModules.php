<?php

namespace Shop\Tills;

use JesseGall\CodeCommandments\Sins\Backend\UndeclaredMember;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * The modules a till is fitted with, each found by the class it is.
 */
final class TillModules
{
    /**
     * @param  array<class-string, object>  $fitted
     */
    public function __construct(private readonly array $fitted) {}

    /**
     * @template TModule of object
     *
     * @param  class-string<TModule>  $type
     * @return TModule
     */
    public function module(string $type): object
    {
        return $this->fitted[$type];
    }

    #[Righteous(UndeclaredMember::class)]
    public function receiptFooter(): string
    {
        return $this->module(ReceiptPrinter::class)->footer();
    }
}

/**
 * The printer a till prints its receipts on.
 */
final class ReceiptPrinter
{
    public function footer(): string
    {
        return 'Thank you for shopping';
    }
}
