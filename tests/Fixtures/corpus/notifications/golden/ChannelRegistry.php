<?php

declare(strict_types=1);

namespace App\Notifications;

/**
 * Keyed store of the channels the dispatcher can deliver through.
 */
final class ChannelRegistry
{
    /**
     * @var array<string, Channel>
     */
    private array $channels = [];

    public function register(string $key, Channel $channel): void
    {
        $this->channels[$key] = $channel;
    }

    public function has(string $key): bool
    {
        return isset($this->channels[$key]);
    }

    public function get(string $key): Channel
    {
        return $this->channels[$key] ?? throw ChannelNotFoundException::forKey($key);
    }
}
