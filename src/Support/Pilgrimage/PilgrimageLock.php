<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pilgrimage;

use JesseGall\CodeCommandments\Support\ConfigLoader;

/**
 * Locks the free-form judging commands while a pilgrimage is in progress. The
 * pilgrimage IS the guided fix workflow; if `judge` / bulk `repent` stay usable
 * mid-walk, agents wander off it (batch by `--prophet`, bulk-absolve, re-scan) and
 * the forward-only guarantees fall apart. While active, the ONLY judging commands
 * allowed are `next` / `todo` / `autofix` (and `absolve` / `report` for the CURRENT
 * finding, `feature-request` to propose a new rule, `abandon` to leave the walk).
 *
 * There is deliberately NO env switch to disable this lock — a blanket bypass an
 * agent could `export` would void the whole guarantee. The gates that genuinely need
 * `judge`'s exit code mid-walk use the narrow internal `judge --gate-probe` mode,
 * which yields a status WITHOUT making `judge` usable for browsing.
 */
final class PilgrimageLock
{
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
     * Resolve the config from the base path, then the current pilgrimage prophet —
     * the convenience the shared services (absolve/report) use to scope themselves to
     * the walk without threading a config path through every caller.
     */
    public static function currentProphetForBasePath(string $basePath): ?string
    {
        return self::currentProphet($basePath, ConfigLoader::resolve(null, $basePath));
    }

    /**
     * If a pilgrimage is active for the CURRENT session, emit the redirect to the
     * pilgrimage commands and return true — the caller should stop. A human in their
     * own terminal (different/no session) is never blocked.
     *
     * @param  callable(string): void  $emit
     */
    public static function blocks(string $basePath, string $command, callable $emit, string $runner = 'commandments '): bool
    {
        if (! PilgrimageState::lockedForCurrentSession($basePath)) {
            return false;
        }

        $emit('');
        $emit("⛔ A pilgrimage is in progress — `{$command}` is locked. Stay on the guided walk:");
        $emit("   {$runner}next            # the current prophet + its remaining locations");
        $emit("   {$runner}todo            # just the file:line list for the current prophet");
        $emit("   {$runner}autofix         # auto-fix the current prophet ([AUTO-FIXABLE] only), then `next`");
        $emit("   {$runner}absolve --at=<file:line> --reason=\"…\"   # DECLINE a genuine false positive (scoped to the current prophet)");
        $emit("   {$runner}report  --at=<file:line> --reason=\"…\"   # DECLINE a wrong rule / prophet bug (scoped, files an issue)");
        $emit("   {$runner}feature-request \"<full proposal text>\"  # propose a NEW rule (the one unscoped action)");
        $emit("   {$runner}abandon         # leave the walk early (judge/repent return; any gate your profile enforces still applies)");
        $emit('absolve/report are how you DECLINE a finding you should not change — not a shortcut past it. Walk to the');
        $emit("end (`next` until complete) and only then is `judge`/`repent` free again. `{$runner}pilgrimage` restarts from");
        $emit('prophet 0 and DISCARDS a completed walk (re-arming the push gate) — use `abandon`, not restart, to step out.');

        return true;
    }
}
