<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Concurrency;

use Closure;

/**
 * A `map` whose iterations run in parallel across forked processes:
 *
 *     $results = Fork::map($items, static fn ($x) => expensive($x));
 *
 * Copy-on-write shares everything built before the fork (a parsed AST, an index)
 * with each child for free; only each item's serializable RETURN value travels
 * back over a socket. Runs sequentially when forking is unavailable, fewer than two
 * items, or already inside a fork (the nesting guard); a worker that fails to fork
 * re-runs its shard inline.
 */
final class Fork
{
    private static bool $inWorker = false;

    /**
     * Apply $fn to each item in parallel, returning results under the original keys
     * in input order. The return value crosses a process boundary, so it must be
     * serializable. $onProgress, if given, is called in the PARENT with the number
     * of items just finished (for a progress bar).
     *
     * @template TKey of array-key
     * @template TIn
     * @template TOut
     * @param  iterable<TKey, TIn>  $items
     * @param  Closure(TIn, TKey): TOut  $fn
     * @param  int|null  $workers  pool size; defaults to the CPU core count
     * @param  Closure(int): void|null  $onProgress
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
     * @param  array<array-key, mixed>  $items
     * @return array<array-key, mixed>
     */
    private static function sequential(array $items, Closure $fn, ?Closure $onProgress): array
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
     * Round-robin the items across $pool shards (so heavy items spread instead of
     * clustering), fork a worker per shard, and merge the results back in input order.
     *
     * @param  array<array-key, mixed>  $items
     * @return array<array-key, mixed>
     */
    private static function forked(array $items, Closure $fn, int $pool, ?Closure $onProgress): array
    {
        $keys = array_keys($items);
        $parent = function_exists('posix_getpid') ? posix_getpid() : null;

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

                // Fork failed — run this shard inline so results stay complete.
                foreach ($shard as $key) {
                    $results[$key] = $fn($items[$key], $key);

                    if ($onProgress !== null) {
                        $onProgress(1);
                    }
                }

                continue;
            }

            if ($pid === 0) {
                self::$inWorker = true;
                fclose($pair[0]);

                // Stream each result the moment it's ready, as a length-prefixed
                // frame, so the parent can tick progress per item — not per shard.
                foreach ($shard as $key) {
                    // Don't grind on as an orphan: if the parent has died (its pid is
                    // no longer ours), stop. Writing to the then-broken result socket
                    // would also SIGPIPE us — this just bails sooner, mid-compute.
                    if ($parent !== null && posix_getppid() !== $parent) {
                        exit(0);
                    }

                    $payload = serialize([$key, $fn($items[$key], $key)]);
                    // Silenced: if the parent died mid-write the pipe is broken (EPIPE);
                    // that's expected teardown, not an error worth a warning.
                    @fwrite($pair[1], pack('N', strlen($payload)) . $payload);
                }

                fclose($pair[1]);
                exit(0);
            }

            fclose($pair[1]);
            $children[$pid] = $pair[0];
        }

        foreach (self::drain($children, $onProgress) as $key => $value) {
            $results[$key] = $value;
        }

        $ordered = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $results)) {
                $ordered[$key] = $results[$key];
            }
        }

        return $ordered;
    }

    /**
     * Read the workers' result frames in COMPLETION order (`stream_select`), so one
     * slow worker never stalls draining the others. Each whole frame is one finished
     * item: collect its result and tick progress by 1 — so the bar moves per item,
     * not per shard. Partial frames are buffered until the rest arrives.
     *
     * @param  array<int, resource>  $children  pid => read socket
     * @return array<array-key, mixed>
     */
    private static function drain(array $children, ?Closure $onProgress): array
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

                // Pull every complete frame out of the buffer (4-byte length + payload).
                while (strlen($buffers[$pid]) >= 4) {
                    $length = unpack('N', substr($buffers[$pid], 0, 4))[1];

                    if (strlen($buffers[$pid]) < 4 + $length) {
                        break;
                    }

                    $frame = @unserialize(substr($buffers[$pid], 4, $length));
                    $buffers[$pid] = substr($buffers[$pid], 4 + $length);

                    if (is_array($frame)) {
                        [$key, $value] = $frame;
                        $results[$key] = $value;

                        if ($onProgress !== null) {
                            $onProgress(1);
                        }
                    }
                }

                if (! feof($sock)) {
                    continue;
                }

                fclose($sock);
                pcntl_waitpid($pid, $status);
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

    private static function canFork(): bool
    {
        return function_exists('pcntl_fork')
            && function_exists('pcntl_waitpid')
            && function_exists('stream_socket_pair');
    }
}
