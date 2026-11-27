<?php

namespace Shop\Recasts;

use JesseGall\CodeCommandments\Testing\Fixed;

#[Fixed(RecastResult::class)]
final readonly class BinLabel
{
    public function __construct(private ?string $code) {}

    public function code(): string
    {
        return (string) $this->code;
    }

    public function printed(): string
    {
        return sprintf('[%s]', $this->code());
    }

    public function upper(): string
    {
        return strtoupper($this->code());
    }
}
