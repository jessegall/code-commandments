<?php

namespace Shop\Integrations;

use JesseGall\CodeCommandments\Sins\Backend\WrappingWithoutCause;

use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Contracts\HttpClient;
use Shop\Exceptions\IntegrationException;

/**
 * Talks to the courier API and rewraps any failure as an IntegrationException —
 * but drops the original exception, losing the stack trace.
 */
final class CourierApi
{
    public function __construct(private readonly HttpClient $http) {}

    #[Sinful(WrappingWithoutCause::class)]
    public function track(string $trackingCode): string
    {
        try {
            return $this->http->get("https://courier.test/track/{$trackingCode}");
        } catch (\Throwable $e) {
            throw new IntegrationException($trackingCode);
        }
    }

    public function reachable(): bool
    {
        return $this->http->get('https://courier.test/ping') !== '';
    }
}
