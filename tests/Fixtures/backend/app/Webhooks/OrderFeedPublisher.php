<?php

namespace Shop\Webhooks;

use JesseGall\CodeCommandments\Detectors\Backend\DataClumpDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

final class OrderFeedPublisher
{
    /**
     * @var list<string>
     */
    private array $pending = [];

    public function queue(string $payload): void
    {
        $this->pending[] = $payload;
    }

    #[Sinful(DataClumpDetector::class)]
    public function publish(string $shopId, string $userId, string $channelId): int
    {
        $sent = count($this->pending);
        $this->pending = [];

        return $sent + strlen($shopId . $userId . $channelId);
    }
}
