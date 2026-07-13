<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks;

use JesseGall\CodeCommandments\Workspace;

/**
 * A tiny persisted counter for ANY recurring hook signal — the shared heartbeat under the reminder
 * hooks. {@see named} builds one from a kebab-case slug (the session's `.{slug}-count` file); drive it
 * by hand ({@see bump}/{@see reset}/{@see clear}) or via `Counter::named($ws, 'my-thing')->due(25)`.
 * Session-scoped ({@see Workspace::path}); the `.*-count` naming convention lets {@see clearAll} wipe
 * the session's counters, so a new counter joins the fresh-session reset just by using {@see named}.
 */
final class Counter
{
    public function __construct(
        private readonly string $path,
        private readonly string $explanation,
        private readonly int $every = 25,
    ) {}

    /**
     * A counter named by a kebab-case $slug — the general factory. Its file is the session's
     * `.{slug}-count`; $describe is an optional one-line note written beneath the count so the file
     * self-documents. $every is this counter's OWN cadence — how many calls between fires
     * ({@see due}/{@see firstThenEvery}) — so each counter picks its own rhythm (a chatty nudge every
     * 10, a steady one every 25).
     */
    public static function named(Workspace $workspace, string $slug, string $describe = '', int $every = 25): self
    {
        return new self(
            $workspace->path('.' . $slug . '-count'),
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
     * Wipe every counter in THIS session's folder — any `.*-count` file under {@see Workspace::sessionDir}.
     * A fresh session calls this so no stale count carries over; any counter built via {@see named} is
     * caught by the naming convention. Deliberately scoped: a fresh session must never wipe a CONCURRENT
     * session's counters — that is the very overwrite this layout exists to prevent.
     */
    public static function clearAll(Workspace $workspace): void
    {
        foreach (glob($workspace->path('.*-count')) ?: [] as $file) {
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
