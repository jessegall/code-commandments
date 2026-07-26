<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * The ONE format every hook marker is written in — a few value lines, a `-----` separator, then a
 * self-describing explanation, so a human who finds the file knows what it is and that deleting it is
 * safe. The state classes ({@see Plan\PlanMarker}, {@see Until\UntilGate}) own WHAT the values mean;
 * this owns how they are read and written, so the format is stated once and never drifts between them.
 * Absence is modelled as the empty value list, never as a missing file a caller has to test for.
 */
final class MarkerFile
{
    private const string SEPARATOR = '-----';

    public function __construct(private readonly string $path) {}

    public function exists(): bool
    {
        return is_file($this->path);
    }

    /**
     * The value lines above the separator — [] when the marker doesn't exist.
     *
     * @return list<string>
     */
    public function values(): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        $lines = [];

        foreach (preg_split('/\R/', (string) file_get_contents($this->path)) ?: [] as $line) {
            if ($line === self::SEPARATOR) {
                break;
            }

            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * The value line at $index, or $default when the marker is absent or truncated above it.
     */
    public function value(int $index, string $default = ''): string
    {
        return $this->values()[$index] ?? $default;
    }

    /**
     * Write $values, followed by the separator and $explanation. Creates the session folder as needed.
     *
     * @param  list<string>  $values
     */
    public function write(array $values, string $explanation): void
    {
        @mkdir(dirname($this->path), 0777, true);
        @file_put_contents($this->path, implode("\n", [...$values, self::SEPARATOR, $explanation]) . "\n");
    }

    public function delete(): void
    {
        @unlink($this->path);
    }

    /**
     * Move this marker to $target, keeping its contents byte-for-byte — how a marker is set aside and
     * brought back (`until pause`/`until resume`) without ever re-serialising, and so re-reading it
     * later can never depend on the writer. False when this marker doesn't exist.
     */
    public function moveTo(self $target): bool
    {
        if (! $this->exists()) {
            return false;
        }

        @mkdir(dirname($target->path), 0777, true);

        return @rename($this->path, $target->path);
    }

    public function path(): string
    {
        return $this->path;
    }
}
