<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pilgrimage;

use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;

/**
 * Persists the cross-file {@see CodebaseIndex} between pilgrimage steps, which run
 * as separate CLI processes that can't share memory. The cache is keyed on a
 * manifest of the scope (each file's path + mtime + size), so an index-needing
 * `next` reuses it instantly while the files are unchanged and rebuilds
 * automatically the moment the agent edits something. Lives at
 * `.commandments/pilgrimage-index.cache`.
 */
final class PilgrimageIndexCache
{
    /**
     * Return a valid index for the scope — from cache when the files are unchanged,
     * freshly built (and re-cached) otherwise.
     *
     * @param  list<string>  $scope
     */
    public function get(string $basePath, array $scope): CodebaseIndex
    {
        $path = self::path($basePath);
        $manifest = $this->manifest($scope);

        if (is_file($path)) {
            $data = @unserialize((string) @file_get_contents($path));

            if (is_array($data) && ($data['manifest'] ?? null) === $manifest && ($data['index'] ?? null) instanceof CodebaseIndex) {
                return $data['index'];
            }
        }

        $index = CodebaseIndex::build($scope);

        @mkdir(dirname($path), 0755, true);
        @file_put_contents($path, serialize(['manifest' => $manifest, 'index' => $index]));

        return $index;
    }

    public static function clear(string $basePath): void
    {
        @unlink(self::path($basePath));
    }

    private static function path(string $basePath): string
    {
        return rtrim($basePath, '/') . '/.commandments/pilgrimage-index.cache';
    }

    /**
     * @param  list<string>  $scope
     */
    private function manifest(array $scope): string
    {
        sort($scope);

        $parts = [];

        foreach ($scope as $file) {
            $parts[] = $file . ':' . (@filemtime($file) ?: 0) . ':' . (@filesize($file) ?: 0);
        }

        return hash('xxh128', implode('|', $parts));
    }
}
