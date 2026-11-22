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
    public function footerOf(TillModule $module): string
    {
        assert($module instanceof ReceiptPrinter);

        return $module->footer();
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    #[Righteous(UndeclaredMember::class)]
    public function signedLines(TillModule $module, array $lines): array
    {
        return $module instanceof ReceiptPrinter
            ? array_map(fn (string $line) => $line.$module->footer(), $lines)
            : $lines;
    }

    #[Righteous(UndeclaredMember::class)]
    public function requiredFooterOf(TillModule $module): string
    {
        abort_unless($module instanceof ReceiptPrinter, 409);

        return $module->footer();
    }

    #[Righteous(UndeclaredMember::class)]
    public function printerFooterOf(TillModule $module): string
    {
        abort_if(! $module instanceof ReceiptPrinter, 409);

        return $module->footer();
    }

    #[Righteous(UndeclaredMember::class)]
    public function wake(RemoteModule $remote): void
    {
        $remote->wake();
    }

    #[Righteous(UndeclaredMember::class)]
    public function receiptFooter(): string
    {
        return $this->module(ReceiptPrinter::class)->footer();
    }
}

/**
 * A device a till can be fitted with.
 */
interface TillModule
{
    public function serial(): string;
}

/**
 * The printer a till prints its receipts on.
 */
final class ReceiptPrinter implements TillModule
{
    public function serial(): string
    {
        return 'RP-1';
    }

    public function footer(): string
    {
        return 'Thank you for shopping';
    }
}

/**
 * The drawer a till keeps its cash in.
 */
final class CashDrawer implements TillModule
{
    public function serial(): string
    {
        return 'CD-1';
    }
}

/**
 * A module on another till, reached by forwarding each call over the shop's network.
 */
final class RemoteModule
{
    /**
     * @param  list<mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return [$method, $arguments];
    }
}
