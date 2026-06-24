<?php

namespace App\NodeConfig;

/**
 * A node's execution config, parsed ONCE at the boundary into typed, total fields.
 */
final readonly class NodeConfig
{
    private const DEFAULT_TIMEOUT_SECONDS = 30;

    private const DEFAULT_RETRIES = 0;

    private const DEFAULT_LABEL = 'Untitled node';

    public function __construct(
        public int $timeoutSeconds,
        public int $retries,
        public string $label,
    ) {}

    /**
     * Total factory: normalize the loose editor bag into typed fields, filling real
     * defaults where it carries nothing. Downstream reads are total.
     */
    public static function from(RawNodeConfig $raw): self
    {
        return new self(
            timeoutSeconds: $raw->timeoutSecondsOr(self::DEFAULT_TIMEOUT_SECONDS),
            retries: $raw->retriesOr(self::DEFAULT_RETRIES),
            label: $raw->labelOr(self::DEFAULT_LABEL),
        );
    }

    public function timeoutMilliseconds(): int
    {
        return $this->timeoutSeconds * 1000;
    }

    public function allowsRetry(): bool
    {
        return $this->retries > 0;
    }
}
