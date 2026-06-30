<?php

namespace Shop\Integrations;

use JesseGall\CodeCommandments\Detectors\Backend\RawDecodedArrayReturnDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Contracts\HttpClient;

/**
 * Talks to the FX API and leaks the decoded payload straight out as an array.
 */
final class ExchangeRateClient
{
    public function __construct(private readonly HttpClient $http) {}

    /**
     * @param  list<string>  $symbols
     * @return array<string, mixed>
     */
    #[Sinful(RawDecodedArrayReturnDetector::class)]
    public function rates(string $base, array $symbols): array
    {
        $query = http_build_query([
            'base' => $base,
            'symbols' => implode(',', $symbols),
        ]);

        return json_decode($this->http->get("https://fx.test/latest?{$query}"), true);
    }
}
