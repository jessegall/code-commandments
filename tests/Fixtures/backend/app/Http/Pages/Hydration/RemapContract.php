<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\HandKeyRemap;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

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

/**
 * The same contract, with the snake_case boundary declared ONCE on the class: `#[MapInputName]` owns the
 * `record_company → recordCompany` translation for every caller.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[Fixed(HandKeyRemap::class)]
final class MappedContractData extends Data
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

    /**
     * The FIX: the source row is passed WHOLE — the class-level `#[MapInputName(SnakeCaseMapper::class)]`
     * does the snake→camel translation, so no caller writes it out again.
     */
    #[Fixed(HandKeyRemap::class)]
    public function importMapped(): MappedContractData
    {
        return MappedContractData::from($this->rows->next());
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
