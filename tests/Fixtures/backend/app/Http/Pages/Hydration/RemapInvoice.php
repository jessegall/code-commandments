<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\HandKeyRemap;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;

/*
 * N7 scenario 3 — a snake→camel remap off a fetched row. Plus the righteous twins: a transformed value and
 * a mixed source, neither of which a single class-level mapper could replace.
 */
final class InvoiceData extends Data
{
    public function __construct(
        public readonly string $invoiceNumber,
        public readonly int $amountCents,
        public readonly string $dueAt,
        public readonly string $taxCode,
    ) {}
}

final class InvoiceGateway
{
    public function fetch(string $id): array
    {
        return [];
    }
}

final class InvoiceReader
{
    public function __construct(private readonly InvoiceGateway $gateway) {}

    #[Sinful(HandKeyRemap::class)]
    public function read(string $id): InvoiceData
    {
        $row = $this->gateway->fetch($id);

        return InvoiceData::from([
            'invoiceNumber' => $row['invoice_number'],
            'amountCents' => $row['amount_cents'],
            'dueAt' => $row['due_at'],
            'taxCode' => $row['tax_code'],
        ]);
    }
}

/**
 * RIGHTEOUS: a transformed value and a value from a DIFFERENT source can't collapse into one class-level
 * `#[MapInputName]`, so the detector must NOT flag them.
 */
final class TweakedInvoiceReader
{
    public function __construct(private readonly InvoiceGateway $gateway) {}

    #[Righteous(HandKeyRemap::class)]
    public function transformed(string $id): InvoiceData
    {
        $row = $this->gateway->fetch($id);

        return InvoiceData::from([
            'invoiceNumber' => strtoupper($row['invoice_number']),
            'amountCents' => $row['amount_cents'],
        ]);
    }

    #[Righteous(HandKeyRemap::class)]
    public function mixed(string $id): InvoiceData
    {
        $row = $this->gateway->fetch($id);
        $meta = $this->gateway->fetch('meta');

        return InvoiceData::from([
            'invoiceNumber' => $row['invoice_number'],
            'amountCents' => $meta['amount_cents'],
        ]);
    }
}
