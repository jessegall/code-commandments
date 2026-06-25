<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Concurrency;

use Throwable;

/**
 * A tiny pcntl fork pool: map a task over a list of items across up to N worker
 * processes and merge their associative results. Workers are forked AFTER the
 * caller has built any shared read-only state (e.g. the codebase index), so they
 * inherit it via copy-on-write — no serialization, no rebuild.
 *
 * Degrades gracefully to in-process execution whenever forking is unavailable
 * (Windows, pcntl missing, a single worker, or too few items), so a consumer
 * never breaks — the result is identical, only slower.
 */
final class ForkPool
{
    public static function isAvailable(): bool
    {
        return PHP_OS_FAMILY !== 'Windows'
            && function_exists('pcntl_fork')
            && function_exists('pcntl_waitpid');
    }

    /**
     * Run $task over $items across up to $workers forks; merge the per-chunk
     * associative results (keys must be unique across chunks). $task receives a
     * chunk (sub-list of $items) and returns an `array<string, mixed>` of
     * plain-serializable values.
     *
     * @param  list<mixed>  $items
     * @param  callable(list<mixed>): array<string, mixed>  $task
     * @return array<string, mixed>
     */
    public static function map(array $items, int $workers, callable $task): array
    {
        $workers = max(1, $workers);

        if ($items === []) {
            return [];
        }

        if ($workers === 1 || count($items) <= 1 || ! self::isAvailable()) {
            return $task($items);
        }

        $chunks = self::chunk($items, min($workers, count($items)));

        $merged = [];
        $children = [];

        foreach ($chunks as $chunk) {
            $file = tempnam(sys_get_temp_dir(), 'ccfork');
            $pid = $file === false ? -1 : @pcntl_fork();

            if ($pid === -1) {
                // Fork (or tempfile) failed — run this chunk in the parent instead.
                if ($file !== false) {
                    @unlink($file);
                }

                $merged += $task($chunk);

                continue;
            }

            if ($pid === 0) {
                // Child: compute, serialize to the temp file, exit without running
                // any shutdown handlers the parent owns.
                $result = [];

                try {
                    $result = $task($chunk);
                } catch (Throwable $e) {
                    $result = ['__fork_error__' => $e->getMessage()];
                }

                @file_put_contents($file, serialize($result));

                // Terminate WITHOUT running inherited userland shutdown handlers
                // (e.g. a test runner's result writer) — the result is already on
                // disk, so a hard kill is safe and keeps the child side-effect-free.
                if (function_exists('posix_kill') && function_exists('posix_getpid')) {
                    posix_kill(posix_getpid(), SIGKILL);
                }

                exit(0);
            }

            $children[] = [$pid, $file];
        }

        foreach ($children as [$pid]) {
            $status = 0;
            pcntl_waitpid($pid, $status);
        }

        foreach ($children as [, $file]) {
            $raw = @file_get_contents($file);
            @unlink($file);

            if ($raw === false || $raw === '') {
                continue;
            }

            $decoded = @unserialize($raw);

            if (is_array($decoded)) {
                $merged += $decoded;
            }
        }

        return $merged;
    }

    /**
     * Split $items into $count contiguous, roughly-equal chunks.
     *
     * @param  list<mixed>  $items
     * @return list<list<mixed>>
     */
    private static function chunk(array $items, int $count): array
    {
        $count = max(1, $count);
        $size = (int) ceil(count($items) / $count);

        return array_values(array_chunk($items, max(1, $size)));
    }
}
