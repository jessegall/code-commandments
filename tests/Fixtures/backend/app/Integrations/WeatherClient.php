<?php

namespace Shop\Integrations;

use JesseGall\CodeCommandments\Sins\Backend\SwallowCatch;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Contracts\HttpClient;

/**
 * Fetches a forecast — but on any failure returns an empty array, manufacturing
 * a "no weather" that's indistinguishable from a real empty response. The strict
 * twin lets the failure propagate.
 */
final class WeatherClient
{
    public function __construct(private readonly HttpClient $http) {}

    /**
     * @return array<string, mixed>
     */
    #[Sinful(SwallowCatch::class)]
    public function forecast(string $city): array
    {
        try {
            $body = $this->http->get("https://weather.test/{$city}");

            return (array) json_decode($body, true);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    #[Righteous(SwallowCatch::class)]
    public function forecastOrThrow(string $city): array
    {
        try {
            $body = $this->http->get("https://weather.test/{$city}");

            return (array) json_decode($body, true);
        } catch (\Throwable $e) {
            report($e);

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function strictForecast(string $city): array
    {
        try {
            $body = $this->http->get("https://weather.test/{$city}");

            return (array) json_decode($body, true);
        } catch (\Throwable $e) {
            throw $e;
        }
    }
}
