<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Caching;

use Illuminate\Filesystem\Filesystem;

/**
 * A short-lived findings cache shared by judge / absolve / the pre-commit hook,
 * so a commit flow does not pay a full prophet pass three times.
 *
 * CONSERVATIVE & SAFE: a cached entry is served ONLY when the entire scroll is
 * byte-identical and the ruleset is unchanged — the cache is keyed per scroll by
 * a `generation` = hash(every scroll file's content + package version + resolved
 * config). Any change to ANY file (so cross-file prophets stay correct) or to
 * the rules busts the whole scroll's cache. So a hit is provably identical to a
 * fresh judge.
 */
final class FindingsCache
{
    /** @var array<string, array{generation: string, files: array<string, array<string, mixed>>}> */
    private array $store = [];

    private bool $loaded = false;

    private ?string $scroll = null;

    public function __construct(
        private readonly string $path,
        private readonly Filesystem $filesystem,
    ) {}

    /**
     * Activate the cache for $scroll at $generation. If the persisted generation
     * differs, the scroll's cache is reset (stale, discarded).
     */
    public function activate(string $scroll, string $generation): void
    {
        $this->load();
        $this->scroll = $scroll;

        if (($this->store[$scroll]['generation'] ?? null) !== $generation) {
            $this->store[$scroll] = ['generation' => $generation, 'files' => []];
        }
    }

    /**
     * Whether $relativePath has a cached entry for the active generation (true
     * even when that file had no findings — it was still judged).
     */
    public function has(string $relativePath): bool
    {
        return $this->scroll !== null && isset($this->store[$this->scroll]['files'][$relativePath]);
    }

    /**
     * The cached encoded judgments for $relativePath (empty array = judged, clean).
     *
     * @return array<string, mixed>
     */
    public function get(string $relativePath): array
    {
        return $this->scroll !== null ? ($this->store[$this->scroll]['files'][$relativePath] ?? []) : [];
    }

    /**
     * @param  array<string, mixed>  $encoded
     */
    public function put(string $relativePath, array $encoded): void
    {
        if ($this->scroll !== null) {
            $this->store[$this->scroll]['files'][$relativePath] = $encoded;
        }
    }

    public function save(): void
    {
        $directory = dirname($this->path);

        if (! $this->filesystem->isDirectory($directory)) {
            $this->filesystem->makeDirectory($directory, 0755, true);
        }

        $this->filesystem->put($this->path, (string) json_encode($this->store, JSON_UNESCAPED_SLASHES));
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;

        if ($this->filesystem->exists($this->path)) {
            $decoded = json_decode((string) $this->filesystem->get($this->path), true);

            if (is_array($decoded)) {
                $this->store = $decoded;
            }
        }
    }
}
