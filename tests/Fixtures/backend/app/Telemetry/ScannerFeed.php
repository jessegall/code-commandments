<?php

namespace Shop\Telemetry;

use JesseGall\CodeCommandments\Sins\Backend\ArrayBagParameter;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use JsonSerializable;

/**
 * Appends what the handheld scanners report to the feed file, one JSON line per scan.
 */
final class ScannerFeed
{
    public function __construct(private readonly string $feedFile) {}

    public function scanned(string $sku, string $bin): void
    {
        $this->emit('scanned', ['sku' => $sku, 'bin' => $bin]);
    }

    public function rejected(string $sku, string $reason): void
    {
        $this->emit('rejected', ['sku' => $sku, 'reason' => $reason]);
    }

    public function counted(ScanCount $count): void
    {
        $this->record($count);
    }

    #[Sinful(ArrayBagParameter::class)]
    private function emit(string $kind, array $payload): void
    {
        file_put_contents($this->feedFile, json_encode(['kind' => $kind, ...$payload]) . "\n", FILE_APPEND);
    }

    #[Fixed(ArrayBagParameter::class)]
    private function record(ScanCount $count): void
    {
        file_put_contents($this->feedFile, json_encode($count) . "\n", FILE_APPEND);
    }
}

/**
 * A bin's stock as a scanner counted it, written to the feed as its own JSON line.
 */
#[Fixed(ArrayBagParameter::class)]
final readonly class ScanCount implements JsonSerializable
{
    public function __construct(
        public string $bin,
        public int $units,
    ) {}

    public function jsonSerialize(): array
    {
        return ['kind' => 'counted', 'bin' => $this->bin, 'units' => $this->units];
    }
}
