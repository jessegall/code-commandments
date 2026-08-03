<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Sins\Backend\ConstructorSideEffect;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Contracts\HttpClient;

/**
 * Warms the export the moment anyone builds one, so merely HAVING a LedgerExport costs a request
 * — and nothing in the code that constructed it asked for that.
 */
#[Sinful(ConstructorSideEffect::class)]
final class LedgerExport
{
    public function __construct(private readonly HttpClient $client, private readonly string $period)
    {
        $this->client->get("/ledger/{$period}/warm");
    }

    public function body(): string
    {
        return $this->client->get("/ledger/{$this->period}");
    }
}

/**
 * The same export, built for free. It holds the client and does the fetching when someone asks
 * for rows, which is the moment the caller chose.
 */
#[Fixed(ConstructorSideEffect::class)]
#[Righteous(ConstructorSideEffect::class)]
final class LazyLedgerExport
{
    public function __construct(private readonly HttpClient $client, private readonly string $period) {}

    public function body(): string
    {
        return $this->client->get("/ledger/{$this->period}");
    }
}
