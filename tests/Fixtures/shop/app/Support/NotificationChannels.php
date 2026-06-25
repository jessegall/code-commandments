<?php

namespace Shop\Support;

final class NotificationChannels
{
    /**
     * @var array<string, object>
     */
    private array $channels = [];

    public function add(string $key, object $channel): void
    {
        $this->channels[$key] = $channel;
    }

    public function get(string $key): ?object
    {
        return $this->channels[$key] ?? null;
    }
}
