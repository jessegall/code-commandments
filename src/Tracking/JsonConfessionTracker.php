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

    /**
     * Finding-level absolutions keyed by content fingerprint.
     *
     * @var array<string, array{absolved_at: string, reason: string|null}>
     */
    protected array $findings = [];

    /**
     * Report-linked finding absolutions keyed by content fingerprint. Unlike
     * `$findings`, these SURVIVE the post-commit reset: a finding the agent
     * reported as wrong (false positive / wrong rule / prophet bug) stays
     * absolved until the upstream issue is answered, at which point
     * `reports --check` releases it. Each entry remembers its issue so the
     * release can be tied to that issue closing.
     *
     * @var array<string, array{reported_at: string, reason: string|null, issue: int|null, repo: string|null}>
     */
    protected array $reported = [];

    /**
     * Fingerprints encountered live during this run (not persisted).
     *
     * @var array<string, true>
     */
    protected array $seen = [];

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

    public function absolveFinding(string $fingerprint, ?string $reason = null): void
    {
        $this->load();

        $this->findings[$fingerprint] = [
            'absolved_at' => date('c'),
            'reason' => $reason,
        ];

        $this->save();
    }

    public function isFindingAbsolved(string $fingerprint): bool
    {
        $this->load();

        return isset($this->findings[$fingerprint]) || isset($this->reported[$fingerprint]);
    }

    public function reportFinding(string $fingerprint, ?string $reason = null, ?int $issue = null, ?string $repo = null): void
    {
        $this->load();

        $this->reported[$fingerprint] = [
            'reported_at' => date('c'),
            'reason' => $reason,
            'issue' => $issue,
            'repo' => $repo,
        ];

        $this->save();
    }

    public function isFindingReported(string $fingerprint): bool
    {
        $this->load();

        return isset($this->reported[$fingerprint]);
    }

    public function releaseReportedFinding(string $fingerprint): void
    {
        $this->load();

        if (isset($this->reported[$fingerprint])) {
            unset($this->reported[$fingerprint]);
            $this->save();
        }
    }

    /**
     * @return array<string, array{reported_at: string, reason: string|null, issue: int|null, repo: string|null}>
     */
    public function reportedFindings(): array
    {
        $this->load();

        return $this->reported;
    }

    public function markFindingSeen(string $fingerprint): void
    {
        $this->seen[$fingerprint] = true;
    }

    public function clearFindingAbsolutions(): int
    {
        $this->load();

        $count = count($this->findings);

        // Report-linked absolutions ($this->reported) are deliberately NOT
        // cleared here: a reported finding stays absolved until its upstream
        // issue is answered (then `reports --check` releases it), so the
        // post-commit reset must leave them in place.
        if ($count > 0) {
            $this->findings = [];
            $this->save();
        }

        return $count;
    }

    public function gcUnseenFindings(): int
    {
        $this->load();

        $removed = 0;

        foreach (array_keys($this->findings) as $fingerprint) {
            if (! isset($this->seen[$fingerprint])) {
                unset($this->findings[$fingerprint]);
                $removed++;
            }
        }

        if ($removed > 0) {
            $this->save();
        }

        return $removed;
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
        $this->findings = [];
        $this->save();
    }

    /**
     * Load absolutions from the tablet (JSON file).
     *
     * Accepts both the current wrapped format ({"files": ..., "findings":
     * ...}) and the legacy flat format (a top-level path => commandment map).
     */
    protected function load(): void
    {
        if ($this->loaded) {
            return;
        }

        if ($this->filesystem->exists($this->tabletPath)) {
            $content = $this->filesystem->get($this->tabletPath);
            $data = json_decode($content, true) ?? [];

            if (array_key_exists('files', $data) || array_key_exists('findings', $data) || array_key_exists('reported', $data)) {
                $this->absolutions = $data['files'] ?? [];
                $this->findings = $data['findings'] ?? [];
                $this->reported = $data['reported'] ?? [];
            } else {
                // Legacy flat format: the whole document is the file map.
                $this->absolutions = $data;
            }
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
            json_encode([
                'files' => $this->absolutions,
                'findings' => $this->findings,
                'reported' => $this->reported,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
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
