<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tracking;

use JesseGall\CodeCommandments\Contracts\ConfessionTracker;
use JesseGall\CodeCommandments\Support\Environment;
use Illuminate\Filesystem\Filesystem;

/**
 * JSON file-based confession tracker.
 * Stores absolution records in a JSON file.
 */
class JsonConfessionTracker implements ConfessionTracker
{
    /**
     * @var array<string, array<string, array{absolved_at: string, reason: string|null, content_hash: string}>>
     */
    protected array $absolutions = [];

    protected bool $loaded = false;

    public function __construct(
        protected string $tabletPath,
        protected Filesystem $filesystem,
    ) {}

    public function absolve(string $filePath, string $commandmentClass, ?string $reason = null): void
    {
        $this->load();

        $normalizedPath = $this->normalizePath($filePath);
        $content = $this->filesystem->get($filePath);
        $contentHash = md5($content);

        if (!isset($this->absolutions[$normalizedPath])) {
            $this->absolutions[$normalizedPath] = [];
        }

        $this->absolutions[$normalizedPath][$commandmentClass] = [
            'absolved_at' => date('c'),
            'reason' => $reason,
            'content_hash' => $contentHash,
        ];

        $this->save();
    }

    public function isAbsolved(string $filePath, string $commandmentClass): bool
    {
        $this->load();

        $normalizedPath = $this->normalizePath($filePath);

        return isset($this->absolutions[$normalizedPath][$commandmentClass]);
    }

    public function revokeAbsolution(string $filePath, string $commandmentClass): void
    {
        $this->load();

        $normalizedPath = $this->normalizePath($filePath);

        if (isset($this->absolutions[$normalizedPath][$commandmentClass])) {
            unset($this->absolutions[$normalizedPath][$commandmentClass]);

            if (empty($this->absolutions[$normalizedPath])) {
                unset($this->absolutions[$normalizedPath]);
            }

            $this->save();
        }
    }

    public function getAbsolutions(string $filePath): array
    {
        $this->load();

        $normalizedPath = $this->normalizePath($filePath);

        return $this->absolutions[$normalizedPath] ?? [];
    }

    public function hasChangedSinceAbsolution(string $filePath, string $commandmentClass, string $currentContent): bool
    {
        $this->load();

        $normalizedPath = $this->normalizePath($filePath);

        if (!isset($this->absolutions[$normalizedPath][$commandmentClass])) {
            return true;
        }

        $storedHash = $this->absolutions[$normalizedPath][$commandmentClass]['content_hash'];

        return md5($currentContent) !== $storedHash;
    }

    /**
     * Get all absolutions across all files.
     *
     * @return array<string, array<string, array{absolved_at: string, reason: string|null, content_hash: string}>>
     */
    public function getAllAbsolutions(): array
    {
        $this->load();

        return $this->absolutions;
    }

    /**
     * Clean up absolutions for files that no longer exist.
     *
     * @return int Number of cleaned entries
     */
    public function cleanup(): int
    {
        $this->load();

        $cleaned = 0;

        foreach (array_keys($this->absolutions) as $filePath) {
            if (!$this->filesystem->exists($filePath)) {
                unset($this->absolutions[$filePath]);
                $cleaned++;
            }
        }

        if ($cleaned > 0) {
            $this->save();
        }

        return $cleaned;
    }

    /**
     * Clear all absolutions.
     */
    public function clear(): void
    {
        $this->absolutions = [];
        $this->save();
    }

    /**
     * Load absolutions from the tablet (JSON file).
     */
    protected function load(): void
    {
        if ($this->loaded) {
            return;
        }

        if ($this->filesystem->exists($this->tabletPath)) {
            $content = $this->filesystem->get($this->tabletPath);
            $this->absolutions = json_decode($content, true) ?? [];
        }

        $this->loaded = true;
    }

    /**
     * Save absolutions to the tablet (JSON file).
     */
    protected function save(): void
    {
        $directory = dirname($this->tabletPath);

        if (!$this->filesystem->isDirectory($directory)) {
            $this->filesystem->makeDirectory($directory, 0755, true);
        }

        $this->filesystem->put(
            $this->tabletPath,
            json_encode($this->absolutions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Normalize the file path to be relative to the base path.
     */
    protected function normalizePath(string $filePath): string
    {
        $basePath = Environment::basePath();

        if (str_starts_with($filePath, $basePath)) {
            return substr($filePath, strlen($basePath) + 1);
        }

        return $filePath;
    }
}
