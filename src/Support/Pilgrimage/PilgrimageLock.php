<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pilgrimage;

/**
 * Locks the free-form judging commands while a pilgrimage is in progress. The
 * pilgrimage IS the guided fix workflow; if `judge` / bulk `repent` stay usable
 * mid-walk, agents wander off it (batch by `--prophet`, bulk-absolve, re-scan) and
 * the forward-only guarantees fall apart. While active, the ONLY judging commands
 * allowed are `next` / `todo` / `autofix` (and `absolve` / `report` / `scripture`
 * for the current finding).
 *
 * Internal callers that legitimately need `judge`'s exit code (the Stop-hook gate
 * probe) set COMMANDMENTS_PILGRIMAGE_BYPASS=1 to skip the lock.
 */
final class PilgrimageLock
{
    public const BYPASS_ENV = 'COMMANDMENTS_PILGRIMAGE_BYPASS';

    /**
     * The short name of the prophet the pilgrimage is currently on, when the walk is
     * locked for THIS session — every action (absolve/report) is scoped to it. Null
     * when no walk is active for this session.
     */
    public static function currentProphet(string $basePath, ?string $configPath): ?string
    {
        if ($configPath === null || ! PilgrimageState::lockedForCurrentSession($basePath)) {
            return null;
        }

        return PilgrimageRunner::fromConfig($basePath, $configPath)->peek()['prophet'] ?? null;
    }

    /**
     * If a pilgrimage is active (and this isn't a bypassed internal call), emit the
     * redirect to the pilgrimage commands and return true — the caller should stop.
     *
     * @param  callable(string): void  $emit
     */
    public static function blocks(string $basePath, string $command, callable $emit, string $runner = 'commandments '): bool
    {
        if (getenv(self::BYPASS_ENV) === '1') {
            return false;
        }

        // Scoped to the AGENT SESSION that started the walk — a human running the same
        // command in their own terminal (different/no session) is never blocked.
        if (! PilgrimageState::lockedForCurrentSession($basePath)) {
            return false;
        }

        $emit('');
        $emit("⛔ A pilgrimage is in progress — `{$command}` is locked. Stay on the guided walk:");
        $emit("   {$runner}next       # the current prophet + its remaining locations");
        $emit("   {$runner}todo       # just the file:line list for the current prophet");
        $emit("   {$runner}autofix    # auto-fix the current prophet ([AUTO-FIXABLE] only), then `next`");
        $emit("   {$runner}absolve --at=<file:line> --prophet=<Name> --reason=\"…\"   # a genuine false positive");
        $emit("   {$runner}report  --prophet=<Name> --at=<file:line> --reason=\"…\"   # a wrong rule / prophet bug");
        $emit('Walk it to the end (`next` until complete), or `pilgrimage` to restart. Only then is `judge`/`repent` available again.');

        return true;
    }
}
