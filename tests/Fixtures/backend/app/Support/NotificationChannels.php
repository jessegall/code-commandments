<?php

namespace Shop\Support;

use JesseGall\CodeCommandments\Detectors\Backend\NullableRegistryLookupDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

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

    #[Sinful(NullableRegistryLookupDetector::class)]
    public function get(string $key): ?object
    {
        return $this->channels[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->channels[$key]);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->channels);
    }

    public function flush(): void
    {
        $this->channels = [];
    }
}
