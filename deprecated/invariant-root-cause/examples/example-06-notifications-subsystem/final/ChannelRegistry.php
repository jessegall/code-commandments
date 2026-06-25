<?php

declare(strict_types=1);

namespace Notifications;

final class ChannelRegistry
{
    /** @var array<string, Channel> */
    private array $channels = [];

    public function register(string $key, Channel $channel): void
    {
        $this->channels[$key] = $channel;
    }

    public function get(string $key): Channel
    {
        return $this->channels[$key]
            ?? throw ChannelNotRegisteredException::forKey($key);
    }

    public function has(string $key): bool
    {
        return isset($this->channels[$key]);
    }
}
