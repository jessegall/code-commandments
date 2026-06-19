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

    /**
     * SMELL: registry returns `?Channel`. A channel key comes from Severity and
     * is wired at boot — a miss is a configuration bug, not a valid "no
     * channel". The `?? null` is a no-op and pushes the contract onto callers.
     */
    public function find(string $key): ?Channel
    {
        return $this->channels[$key] ?? null;
    }
}
