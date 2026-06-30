<?php

namespace Shop\Shipping;

use Shop\Contracts\HttpClient;
use Shop\Data\TrackingStatus;

/**
 * The righteous twin: talks to the same API, but pins the response down into a
 * typed value object at the boundary so nothing downstream sees a loose array.
 */
final class CourierGateway
{
    public function __construct(private readonly HttpClient $http) {}

    public function track(string $trackingCode): TrackingStatus
    {
        $body = $this->http->get("https://courier.test/v1/track/{$trackingCode}");

        return TrackingStatus::from(json_decode($body, true));
    }
}
