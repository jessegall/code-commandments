<?php

namespace Shop\Integrations;

use JesseGall\CodeCommandments\Sins\Backend\RethrownUnchanged;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Contracts\HttpClient;
use Shop\Exceptions\IntegrationException;

/**
 * Reads a supplier's stock feed. The first method's catch holds the failure for a moment and
 * throws it straight back, so the try promises handling where there is none; the one below it
 * earns its clause by throwing a failure of its own and carrying the original as the cause.
 */
final class SupplierFeed
{
    public function __construct(private readonly HttpClient $http) {}

    #[Sinful(RethrownUnchanged::class)]
    public function read(string $supplier): string
    {
        try {
            return $this->http->get("https://supplier.test/feed/{$supplier}");
        } catch (\JsonException $e) {
            throw $e;
        }
    }

    #[Righteous(RethrownUnchanged::class)]
    public function readFeed(string $supplier): string
    {
        try {
            return $this->http->get("https://supplier.test/feed/{$supplier}");
        } catch (\JsonException $e) {
            throw new IntegrationException($supplier, 0, $e);
        }
    }
}
