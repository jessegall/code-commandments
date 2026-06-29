<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Detectors\Backend\RawDecodedArrayReturnDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Contracts\HttpClient;

/**
 * Talks to the courier's tracking API.
 */
final class ParcelTracker
{
    public function __construct(private readonly HttpClient $http) {}

    /**
     * @return array<string, mixed>
     */
    #[Sinful(RawDecodedArrayReturnDetector::class)]
    public function track(string $trackingCode): array
    {
        $body = $this->http->get("https://courier.test/v1/track/{$trackingCode}");

        return json_decode($body, true);
    }

    public function isDelivered(string $trackingCode): bool
    {
        $status = $this->http->get("https://courier.test/v1/track/{$trackingCode}/status");

        return str_contains($status, 'delivered');
    }
}
