<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\HandKeyRemap;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;

/*
 * N7 scenario 1 — a hand-written snake→camel remap off one source, which a class-level `#[MapInputName]`
 * owns. The source is read from a row provider.
 */
final class ContractData extends Data
{
    public function __construct(
        public readonly string $recordCompany,
        public readonly string $signedAt,
    ) {}
}

final class ContractRows
{
    public function next(): array
    {
        return [];
    }
}

final class ContractImporter
{
    public function __construct(private readonly ContractRows $rows) {}

    #[Sinful(HandKeyRemap::class)]
    public function import(): ContractData
    {
        $src = $this->rows->next();

        return ContractData::from([
            'recordCompany' => $src['record_company'],
            'signedAt' => $src['signed_at'],
        ]);
    }

    public function drain(): int
    {
        $imported = 0;

        while ($this->rows->next() !== []) {
            $imported++;
        }

        return $imported;
    }
}
