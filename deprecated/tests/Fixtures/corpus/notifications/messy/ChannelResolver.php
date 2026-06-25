<?php

namespace App\Notifications;

use Exception;

// resolves a channel via name-string classification + nullable returns
class ChannelResolver
{
    /**
     * @param array<string,mixed> $config
     */
    public function resolve($type, array $config = [])
    {
        $store = new ChannelStore();

        // type-name classification with in_array on class-ish names
        if (in_array($type, ['Stripe', 'Paypal'])) {
            // these are not real channels — fall back
            $type = 'email';
        }

        $key = NotificationType::channelKey($type);
        $channel = $store->get($key);

        if ($channel == null) {
            $channel = $store->build(ucfirst($key));
        }

        if ($channel == null) {
            throw new Exception('no channel for ' . $type);
        }

        return $channel;
    }

    /**
     * @return array<string,mixed>
     */
    public function describe($type)
    {
        $urgent = NotificationType::isUrgent($type);

        return [
            'type' => $type,
            'urgent' => $urgent,
            'key' => NotificationType::channelKey($type),
        ];
    }
}
