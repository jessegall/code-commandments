<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Caching;

use Illuminate\Filesystem\Filesystem;

/**
 * A short-lived findings cache shared by judge / absolve / the pre-commit hook,
 * so a commit flow does not pay a full prophet pass three times.
 *
 * Two stores, split by prophet kind so a cleanup pass isn't a cold scan on every
 * edit:
 *  - CROSS-file prophets ({@see \JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex})
 *    are keyed per scroll by a `generation` = hash(every scroll file's content +
 *    version + config). Any file change busts the whole scroll's cross cache, so
 *    a cross-file finding (duplicate detection, origin traces) is always correct.
 *  - SINGLE-file prophets depend ONLY on their own file, so they are
 *    CONTENT-ADDRESSED: keyed by hash(that file's content + ruleset). Editing one
 *    file leaves every OTHER file's single-file findings cached — that is the win.
 * Either way a served entry is provably identical to a fresh judge.
 */
final class FindingsCache
{
    /** Persisted format version — bump to discard incompatible on-disk caches. */
    private const FORMAT = 2;

    /** @var array<string, array{generation: string, files: array<string, array<string, mixed>>}> */
    private array $store = [];

    /** @var array<string, array<string, array{key: string, encoded: array<string, mixed>}>> scroll => path => entry */
    private array $single = [];

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

    /**
     * Whether $path has a cached single-file entry under the active scroll matching
     * $key (the hash of this file's content + ruleset). Survives other files
     * changing — only a change to THIS file or the rules busts it.
     */
    public function hasSingle(string $path, string $key): bool
    {
        $this->load();

        return $this->scroll !== null && ($this->single[$this->scroll][$path]['key'] ?? null) === $key;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSingle(string $path): array
    {
        return $this->scroll !== null ? ($this->single[$this->scroll][$path]['encoded'] ?? []) : [];
    }

    /**
     * @param  array<string, mixed>  $encoded
     */
    public function putSingle(string $path, string $key, array $encoded): void
    {
        if ($this->scroll !== null) {
            // One entry per path (latest content) — old hashes never recur.
            $this->single[$this->scroll][$path] = ['key' => $key, 'encoded' => $encoded];
        }
    }

    public function save(): void
    {
        $directory = dirname($this->path);

        if (! $this->filesystem->isDirectory($directory)) {
            $this->filesystem->makeDirectory($directory, 0755, true);
        }

        $payload = ['format' => self::FORMAT, 'cross' => $this->store, 'single' => $this->single];

        $this->filesystem->put($this->path, (string) json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;

        if (! $this->filesystem->exists($this->path)) {
            return;
        }

        $decoded = json_decode((string) $this->filesystem->get($this->path), true);

        // Only honour the current format; an older/foreign cache is discarded
        // (it regenerates on the next judge — the cache is disposable).
        if (is_array($decoded) && ($decoded['format'] ?? null) === self::FORMAT) {
            $this->store = is_array($decoded['cross'] ?? null) ? $decoded['cross'] : [];
            $this->single = is_array($decoded['single'] ?? null) ? $decoded['single'] : [];
        }
    }
}
