<?php

namespace Shop\Integrations;

use Shop\Contracts\HttpClient;
use Shop\Exceptions\IntegrationException;

/**
 * Righteous twin for WrappingWithoutCause: wraps the failure but chains the
 * original exception as the cause, so the trace survives.
 */
final class ResilientCourier
{
    public function __construct(private readonly HttpClient $http) {}

    public function track(string $trackingCode): string
    {
        try {
            return $this->http->get("https://courier.test/track/{$trackingCode}");
        } catch (\Throwable $e) {
            throw new IntegrationException($trackingCode, 0, $e);
        }
    }
}
