<?php

namespace App\Notifications;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationDispatcher
{
    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function dispatch(array $data)
    {
        // app() service location + new-ing collaborators inside a service
        $store = app(ChannelStore::class);

        extract($data);

        $type = $data['type'] ?? 'email';
        $retries = (int) ($data['retries'] ?? 1);

        // string comparison closed-set classification
        if ($type == 'email') {
            $channel = new EmailChannel();
        } elseif (in_array($type, ['sms', 'push'])) {
            $channel = $store->build(ucfirst($type));
        } else {
            $channel = null;
        }

        if ($channel == null) {
            Log::warning('no channel for ' . $type);

            return ['ok' => false];
        }

        $message = new Message($data);

        Cache::put('last_notification', $message->toArray(), 60);
        DB::table('notifications')->insert($message->toArray());

        for ($i = 0; $i < $retries; $i++) {
            $channel->send($message->recipient, $message->toArray());
        }

        return compact('type', 'retries');
    }
}
