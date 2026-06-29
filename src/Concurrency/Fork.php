<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Concurrency;

use Closure;

/**
 * Run a closure over every item of an iterable in parallel, across a pool of OS
 * processes, and get back the accumulated results in input order — a `map` whose
 * iterations run on different cores.
 *
 *     $results = Fork::map($candidates, static fn ($c) => expensiveCheck($c));
 *
 * PHP has no shared-memory threads in a stock build, so "parallel" here means
 * `pcntl_fork`. The win comes from copy-on-write: everything the parent built
 * before the fork — a parsed AST, an index, the `$items` themselves — is shared
 * with each child for free; only each item's (small, serializable) RETURN value
 * travels back over a socket. So the closure may close over a huge `$codebase`
 * without paying to copy it.
 *
 * Two guard rails keep it safe to drop in anywhere:
 *   - **Sequential fallback** — fewer than two items, forking unavailable
 *     (Windows / hardened CLI), or a pool of one ⇒ a plain `array_map`, same result.
 *   - **Nesting guard** — once inside a forked child, a nested `Fork::map` also runs
 *     sequentially, so a parallel detector called from a parallel runner can't fork
 *     a bomb. The outermost `map` owns the cores.
 *
 * A worker that dies (fork/pair failure, crash) has its shard re-run inline in the
 * parent, so a partial failure degrades to slower — never to missing results.
 */
final class Fork
{
    /** True inside a forked child — makes a nested {@see map()} run sequentially. */
    private static bool $inWorker = false;

    /**
     * Apply $fn to each item, in parallel, and return the results keyed by the
     * original keys (insertion order preserved). The closure receives `($value,
     * $key)`; its return value must be serializable (it crosses a process
     * boundary) — return plain data, never an AST node or a resource.
     *
     * Pass $onProgress to be told, IN THE PARENT, how many items a worker just
     * finished — fired once per shard as its results arrive (or per item on the
     * sequential path). It's for a progress bar; it never sees the results and must
     * not assume an order.
     *
     * @template TKey of array-key
     * @template TIn
     * @template TOut
     * @param  iterable<TKey, TIn>  $items
     * @param  Closure(TIn, TKey): TOut  $fn
     * @param  int|null  $workers  pool size; defaults to the CPU core count
     * @param  Closure(int): void|null  $onProgress  parent-side: items just completed
     * @return array<TKey, TOut>
     */
    public static function map(iterable $items, Closure $fn, ?int $workers = null, ?Closure $onProgress = null): array
    {
        $items = is_array($items) ? $items : iterator_to_array($items);

        $pool = min($workers ?? self::cpuCount(), count($items));

        if ($pool < 2 || self::$inWorker || ! self::canFork()) {
            return self::sequential($items, $fn, $onProgress);
        }

        return self::forked($items, $fn, $pool, $onProgress);
    }

    /**
     * @template TKey of array-key
     * @template TIn
     * @template TOut
     * @param  array<TKey, TIn>  $items
     * @param  Closure(TIn, TKey): TOut  $fn
     * @param  Closure(int): void|null  $onProgress
     * @return array<TKey, TOut>
     */
    private static function sequential(array $items, Closure $fn, ?Closure $onProgress = null): array
    {
        $out = [];

        foreach ($items as $key => $value) {
            $out[$key] = $fn($value, $key);

            if ($onProgress !== null) {
                $onProgress(1);
            }
        }

        return $out;
    }

    /**
     * Round-robin the items across $pool shards, fork a worker per shard, and merge
     * the results back. Round-robin (not contiguous chunks) so a cluster of
     * expensive items spreads across workers instead of landing on one.
     *
     * @template TKey of array-key
     * @template TIn
     * @template TOut
     * @param  array<TKey, TIn>  $items
     * @param  Closure(TIn, TKey): TOut  $fn
     * @param  Closure(int): void|null  $onProgress
     * @return array<TKey, TOut>
     */
    private static function forked(array $items, Closure $fn, int $pool, ?Closure $onProgress = null): array
    {
        $keys = array_keys($items);

        /** @var list<list<TKey>> $shards */
        $shards = array_fill(0, $pool, []);

        foreach ($keys as $i => $key) {
            $shards[$i % $pool][] = $key;
        }

        $children = [];
        $results = [];

        foreach ($shards as $shard) {
            if ($shard === []) {
                continue;
            }

            $pair = @stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            $pid = $pair === false ? -1 : pcntl_fork();

            if ($pid === -1) {
                if ($pair !== false) {
                    fclose($pair[0]);
                    fclose($pair[1]);
                }

                // Forking failed — run this shard inline so results stay complete.
                foreach ($shard as $key) {
                    $results[$key] = $fn($items[$key], $key);
                }

                if ($onProgress !== null) {
                    $onProgress(count($shard));
                }

                continue;
            }

            if ($pid === 0) {
                self::$inWorker = true;
                fclose($pair[0]);

                $slice = [];
                foreach ($shard as $key) {
                    $slice[$key] = $fn($items[$key], $key);
                }

                fwrite($pair[1], serialize($slice));
                fclose($pair[1]);
                exit(0);
            }

            fclose($pair[1]);
            $children[$pid] = $pair[0];
        }

        foreach (self::drain($children, $onProgress) as $key => $value) {
            $results[$key] = $value;
        }

        // Restore input order — round-robin shards and completion-order draining
        // both scramble it; the caller asked for a map, so hand back map order.
        $ordered = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $results)) {
                $ordered[$key] = $results[$key];
            }
        }

        return $ordered;
    }

    /**
     * Read every worker's serialized shard, in COMPLETION order (not fork order):
     * `stream_select` hands back whichever socket is ready, so one slow worker
     * never stalls draining the others. Each worker's payload is buffered until its
     * socket hits EOF (the child closed it).
     *
     * @param  array<int, resource>  $children  pid => read socket
     * @param  Closure(int): void|null  $onProgress
     * @return array<array-key, mixed>
     */
    private static function drain(array $children, ?Closure $onProgress = null): array
    {
        $buffers = [];

        foreach ($children as $pid => $sock) {
            stream_set_blocking($sock, false);
            $buffers[$pid] = '';
        }

        $results = [];

        while ($children !== []) {
            $read = array_values($children);
            $write = $except = null;

            if (@stream_select($read, $write, $except, null) === false) {
                break;
            }

            foreach ($read as $sock) {
                $pid = array_search($sock, $children, true);

                if ($pid === false) {
                    continue;
                }

                $chunk = fread($sock, 65536);

                if (is_string($chunk) && $chunk !== '') {
                    $buffers[$pid] .= $chunk;
                }

                if (! feof($sock)) {
                    continue;
                }

                fclose($sock);
                pcntl_waitpid($pid, $status);

                $slice = $buffers[$pid] !== '' ? @unserialize($buffers[$pid]) : [];

                if (is_array($slice)) {
                    foreach ($slice as $key => $value) {
                        $results[$key] = $value;
                    }

                    if ($onProgress !== null) {
                        $onProgress(count($slice));
                    }
                }

                unset($children[$pid], $buffers[$pid]);
            }
        }

        // If select bailed out, reap whatever's left so no child is left a zombie.
        foreach (array_keys($children) as $pid) {
            @fclose($children[$pid]);
            pcntl_waitpid($pid, $status);
        }

        return $results;
    }

    /**
     * The CPU core count — the natural pool size. Falls back to 1 (forking off) when
     * it can't be read.
     */
    private static function cpuCount(): int
    {
        foreach (['nproc 2>/dev/null', 'sysctl -n hw.ncpu 2>/dev/null'] as $command) {
            $value = trim((string) @shell_exec($command));

            if ($value !== '' && ctype_digit($value)) {
                return max(1, (int) $value);
            }
        }

        return 1;
    }

    /**
     * Is process forking available on this build? (`pcntl` + socket pairs — absent
     * on Windows and some hardened CLIs, where `map` runs sequentially.)
     */
    private static function canFork(): bool
    {
        return function_exists('pcntl_fork')
            && function_exists('pcntl_waitpid')
            && function_exists('stream_socket_pair');
    }
}
