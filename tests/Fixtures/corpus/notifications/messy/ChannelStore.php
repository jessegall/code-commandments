<?php

namespace App\Notifications;

// keyed store, but NOT a *Registry; nullable get, no has()
class ChannelStore
{
    public $items = [];

    public function add($key, $channel)
    {
        $this->items[$key] = $channel;
    }

    public function get($key)
    {
        return $this->items[$key] ?? null;
    }

    public function build($type)
    {
        // type-name classification via in_array
        if (in_array($type, ['Email', 'EmailChannel'])) {
            return new EmailChannel();
        } elseif (in_array($type, ['Sms', 'SmsChannel'])) {
            return new SmsChannel();
        } elseif (in_array($type, ['Push', 'PushChannel'])) {
            return new PushChannel();
        }

        return null;
    }
}
