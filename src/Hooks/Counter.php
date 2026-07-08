<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks;

/**
 * A tiny persisted counter for ANY recurring hook signal — the shared heartbeat machinery under the
 * reminder hooks, and the easy way to add a new one. Build one with {@see named} (a kebab-case slug names
 * it; the count lives on the first line of `.commandments/.{slug}-count`, with a self-describing note
 * below that the `(int)` read ignores), then either drive it by hand ({@see bump}/{@see reset}/{@see clear})
 * or use the {@see due} one-liner for the common "fire once every N" cadence:
 *
 *     if (Counter::named($root, 'my-thing')->due(25)) { …surface the note… }
 *
 * Worktree-scoped by its file path. Every counter follows the `.commandments/.*-count` naming convention,
 * so {@see clearAll} can wipe them ALL on a fresh session without any hook registering its path — a new
 * counter joins the reset for free just by being created through {@see named}.
 */
final class Counter
{
    public function __construct(
        private readonly string $path,
        private readonly string $explanation,
        private readonly int $every = 25,
    ) {}

    /**
     * A counter named by a kebab-case $slug — the general factory. Its file is `.commandments/.{slug}-count`;
     * $describe is an optional one-line note written beneath the count so the file self-documents. $every is
     * this counter's OWN cadence — how many calls between fires ({@see due}/{@see firstThenEvery}) — so each
     * counter picks its own rhythm (a chatty nudge every 10, a steady one every 25).
     */
    public static function named(string $root, string $slug, string $describe = '', int $every = 25): self
    {
        return new self(
            $root . '/.commandments/.' . $slug . '-count',
            self::note($slug, $describe),
            $every,
        );
    }

    /**
     * Increment the persisted count and return the new value.
     */
    public function bump(): int
    {
        $count = 1 + (is_file($this->path) ? (int) file_get_contents($this->path) : 0);
        $this->write($count);

        return $count;
    }

    /**
     * The heartbeat one-liner: bump, and return true once the count reaches the cadence — resetting on that
     * tick so it fires once every N calls. False (and silent) on the other N-1. Pass $every to override this
     * counter's configured cadence for the call.
     */
    public function due(?int $every = null): bool
    {
        if ($this->bump() < ($every ?? $this->every)) {
            return false;
        }

        $this->reset();

        return true;
    }

    /**
     * Fire on the FIRST call and then once every N after — for a nudge that should land the first time
     * something happens (this session, since a fresh session {@see clearAll}s the count) and then only
     * periodically. Modulo-based, so it never resets. Pass $every to override the configured cadence.
     */
    public function firstThenEvery(?int $every = null): bool
    {
        $count = $this->bump();
        $every = $every ?? $this->every;

        return $count === 1 || ($every > 0 && $count % $every === 0);
    }

    public function reset(): void
    {
        $this->write(0);
    }

    public function clear(): void
    {
        @unlink($this->path);
    }

    /**
     * Wipe every counter in the worktree — any `.commandments/.*-count` file. A fresh session calls this so
     * no stale count carries over; any counter built via {@see named} is caught by the naming convention.
     */
    public static function clearAll(string $root): void
    {
        foreach (glob($root . '/.commandments/.*-count') ?: [] as $file) {
            @unlink($file);
        }
    }

    private function write(int $count): void
    {
        @mkdir(dirname($this->path), 0777, true);
        @file_put_contents($this->path, $count . "\n" . $this->explanation . "\n");
    }

    private static function note(string $slug, string $describe): string
    {
        $tail = $describe === '' ? '' : " It {$describe}.";

        return "-----\nCode-commandments counter `{$slug}` (a hook heartbeat). The number on the first line is "
            . "the running count.{$tail} Safe to delete — it regenerates.";
    }
}
