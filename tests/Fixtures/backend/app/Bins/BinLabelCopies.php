<?php

namespace Shop\Bins;

use JesseGall\CodeCommandments\Sins\Backend\LoneImplementerInterface;
use JesseGall\CodeCommandments\Sins\Backend\UndeclaredMember;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;

interface BinLabel
{
    public function text(): string;
}

#[Fixed(UndeclaredMember::class)]
#[Sinful(LoneImplementerInterface::class)]
interface ScannableBinLabel extends BinLabel
{
    public string $barcode { get; }
}

final readonly class PrintedBinLabel implements ScannableBinLabel
{
    public function __construct(public string $barcode)
    {
    }

    public function text(): string
    {
        return 'Bin '.$this->barcode;
    }
}

final readonly class TemporaryBinLabel implements BinLabel
{
    public function __construct(private string $aisle, private int $bin)
    {
    }

    public function text(): string
    {
        return sprintf('%s-%d', $this->aisle, $this->bin);
    }
}

final class BinLabelCopies
{
    #[Sinful(UndeclaredMember::class)]
    public function copy(BinLabel $label): string
    {
        return $label->text().' ('.$label->barcode.')';
    }

    #[Fixed(UndeclaredMember::class)]
    public function copyScannable(ScannableBinLabel $label): string
    {
        return $label->text().' ('.$label->barcode.')';
    }
}
