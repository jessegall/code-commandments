<?php

namespace Shop\Integrations;

use JesseGall\CodeCommandments\Sins\Backend\RawDecodedArrayReturn;

use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Reads the nightly supplier stock dump off disk and hands back the decoded rows
 * untyped — a different boundary (a file, not HTTP), same untyped leak.
 */
final class InventoryFeedClient
{
    public function __construct(private readonly string $feedDirectory) {}

    /**
     * @return array<string, mixed>
     */
    #[Sinful(RawDecodedArrayReturn::class)]
    public function snapshot(): array
    {
        $path = rtrim($this->feedDirectory, '/') . '/inventory.json';
        $contents = file_get_contents($path);

        return json_decode($contents);
    }

    public function isStale(): bool
    {
        $path = rtrim($this->feedDirectory, '/') . '/inventory.json';

        return ! file_exists($path) || filemtime($path) < time() - 86400;
    }

    public function skuFile(string $sku): string
    {
        return rtrim($this->feedDirectory, '/') . "/skus/{$sku}.json";
    }
}
