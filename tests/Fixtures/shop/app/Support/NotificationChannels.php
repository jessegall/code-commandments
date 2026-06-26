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
}
