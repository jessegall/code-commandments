<?php

namespace App\RegistryCorpus;

use App\Notifications\InvoicePaidNotification;
use App\Notifications\Notification;
use App\Notifications\ShipmentNotification;
use App\Notifications\WelcomeNotification;

/**
 * REGISTRY: no — a factory: make() constructs a BRAND-NEW Notification per call
 * via a match, holding no keyed store you put into and look up from.
 */
class NotificationFactory
{
    public function make(string $type, array $payload = []): Notification
    {
        return match ($type) {
            'welcome' => new WelcomeNotification($payload['name'] ?? 'there'),
            'invoice.paid' => new InvoicePaidNotification($payload['invoice_id']),
            'shipment' => new ShipmentNotification($payload['tracking'], $payload['carrier'] ?? 'ups'),
            default => throw new \InvalidArgumentException("Unknown notification type [{$type}]."),
        };
    }
}
