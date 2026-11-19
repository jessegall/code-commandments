<?php

namespace Shop\Shipping\Tracking;

use JesseGall\CodeCommandments\Sins\Backend\MiddleMan;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Asks the carrier where a parcel is. Every method hands its tracking code straight to the carrier client.
 */
#[Sinful(MiddleMan::class)]
final class ParcelTracker
{
    public function __construct(private readonly CarrierClient $client) {}

    public function status(string $tracking): string
    {
        return $this->client->status($tracking);
    }

    public function hoursToDelivery(string $tracking): int
    {
        return $this->client->hoursToDelivery($tracking);
    }
}

/**
 * The tracking page, asking the carrier client itself rather than a class that only passes the question on.
 */
#[Fixed(MiddleMan::class)]
final class TrackingPage
{
    public function __construct(private readonly CarrierClient $client) {}

    public function headline(string $tracking): string
    {
        return sprintf('%s, %d hours to go', $this->client->status($tracking), $this->client->hoursToDelivery($tracking));
    }
}
