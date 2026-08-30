<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks;

use JesseGall\CodeCommandments\Cli\State\Legend;
use JesseGall\CodeCommandments\Cli\State\State;
use JesseGall\CodeCommandments\Cli\State\StateFile;
use JesseGall\CodeCommandments\Workspace;

/**
 * A tiny persisted counter for ANY recurring hook signal — the shared heartbeat under the reminder
 * hooks. {@see named} builds one from a kebab-case slug (the session's `.{slug}-count` file); drive it
 * by hand ({@see bump}/{@see reset}/{@see clear}) or via `Counter::named($ws, 'my-thing')->due(25)`.
 * Session-scoped ({@see Workspace::path}) and written in the shared {@see StateFile} format, so the
 * number carries its name and meaning; the `.*-count` convention lets {@see clearAll} wipe them all.
 * Use it only for a counter that stands ALONE — one belonging to a larger state is a named value in
 * that state's own file, so it cannot outlive the thing it counts.
 */
final class Counter
{
    public function __construct(
        private readonly StateFile $file,
        private readonly int $every = 25,
    ) {}

    /**
     * A counter named by a kebab-case $slug — the general factory. Its file is the session's
     * `.{slug}-count`; $describe is an optional one-line note written into the legend so the file
     * self-documents. $every is this counter's OWN cadence — how many calls between fires
     * ({@see due}/{@see firstThenEvery}) — so each counter picks its own rhythm (a chatty nudge every
     * 10, a steady one every 25).
     */
    public static function named(Workspace $workspace, string $slug, string $describe = '', int $every = 25): self
    {
        return new self(
            new StateFile($workspace->path('.' . $slug . '-count'), self::legend($slug, $describe)),
            $every,
        );
    }

    private static function legend(string $slug, string $describe): Legend
    {
        return new Legend(
            "Code-commandments counter `{$slug}` — a hook heartbeat.",
            ['count' => 'the running count' . ($describe === '' ? '' : ". It {$describe}")],
            defaults: new State(count: 0),
        );
    }

    /**
     * Increment the persisted count and return the new value.
     */
    public function bump(): int
    {
        $count = 1 + $this->count();
        $this->write($count);

        return $count;
    }

    /**
     * The count as it stands, WITHOUT touching it — for a caller that has to READ the heartbeat rather
     * than drive it ("has anything happened since this counter was last reset?"). Zero when the counter
     * has never been bumped this session.
     */
    public function count(): int
    {
        return $this->file->read()->int('count');
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
        $this->file->delete();
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

    /**
     * Has $work moved on by at least $stretch since this last marked it? Answering yes MARKS it, so a
     * signal paced this way speaks once per stretch of work rather than once per firing — and a stretch
     * with nothing in it says nothing at all. The first asking is always owed: a reader who has never
     * been told cannot be repeating.
     */
    public function movedBy(int $work, ?int $stretch = null): bool
    {
        // Held one PAST the mark, so "never marked" and "marked before any work was done" stay different
        // facts. Sharing 0 between them makes a signal fire for ever at the start of a session — which is
        // precisely when nothing has happened worth saying.
        $marked = $this->count();

        if ($marked > 0 && $work - ($marked - 1) < ($stretch ?? $this->every)) {
            return false;
        }

        $this->write($work + 1);

        return true;
    }

    private function write(int $count): void
    {
        $this->file->write(new State(count: $count));
    }
}
