<?php

namespace Shop\Notifications;

use JesseGall\CodeCommandments\Detectors\Backend\SwallowCatchDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Fire-and-forget Slack ping that swallows any failure with an empty catch — the
 * error vanishes and nobody ever knows the ping was lost.
 */
final class SlackNotifier
{
    /** @var list<string> */
    private array $channels = ['#ops', '#alerts'];

    public function __construct(private readonly string $webhookUrl) {}

    #[Sinful(SwallowCatchDetector::class)]
    public function ping(string $message): void
    {
        foreach ($this->channels as $channel) {
            try {
                $this->deliver($channel, $message);
            } catch (\Throwable $e) {
            }
        }
    }

    public function isConfigured(): bool
    {
        return $this->webhookUrl !== '' && $this->channels !== [];
    }

    private function deliver(string $channel, string $message): void {}
}
