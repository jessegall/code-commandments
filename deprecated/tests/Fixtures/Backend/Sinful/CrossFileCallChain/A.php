<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\CrossFileCallChain;

/**
 * Entry point in the A → B → C chain: constructs the array from an
 * external source (`json_decode`) and hands it off. This is where the DTO
 * should be introduced.
 */
class A
{
    public function __construct(
        private readonly B $b,
    ) {}

    public function ingest(string $raw): string
    {
        $payload = json_decode($raw, true);

        return $this->b->relay($payload);
    }
}
