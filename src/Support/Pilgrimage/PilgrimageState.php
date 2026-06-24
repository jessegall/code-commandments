<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pilgrimage;

/**
 * The persisted position of a forward-only pilgrimage: which doctrine, which
 * pillar within it, and which PROPHET within that pillar the agent is on, plus the
 * frozen file scope for the whole walk. `next` re-scans only the current prophet to
 * verify it is resolved before advancing — and never re-scans a prophet already
 * passed, so the agent can only go forward (the fix-A-breaks-B-breaks-A loop is
 * impossible). State lives at `.commandments/pilgrimage.json`.
 */
final class PilgrimageState
{
    /**
     * @param  list<string>  $scope  frozen file list for the whole walk
     */
    public function __construct(
        public int $doctrine = 0,
        public int $pillar = 0,
        public int $prophet = 0,
        public array $scope = [],
        public string $scroll = 'backend',
        public bool $complete = false,
    ) {}

    public static function path(string $basePath): string
    {
        return rtrim($basePath, '/') . '/.commandments/pilgrimage.json';
    }

    public static function load(string $basePath): ?self
    {
        $path = self::path($basePath);

        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data)) {
            return null;
        }

        return new self(
            (int) ($data['doctrine'] ?? 0),
            (int) ($data['pillar'] ?? 0),
            (int) ($data['prophet'] ?? 0),
            is_array($data['scope'] ?? null) ? $data['scope'] : [],
            (string) ($data['scroll'] ?? 'backend'),
            (bool) ($data['complete'] ?? false),
        );
    }

    public function save(string $basePath): void
    {
        $path = self::path($basePath);
        @mkdir(dirname($path), 0755, true);

        file_put_contents($path, json_encode([
            'doctrine' => $this->doctrine,
            'pillar' => $this->pillar,
            'prophet' => $this->prophet,
            'scope' => $this->scope,
            'scroll' => $this->scroll,
            'complete' => $this->complete,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }

    public static function clear(string $basePath): void
    {
        @unlink(self::path($basePath));
    }
}
