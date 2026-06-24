<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * The single orchestration entry point behind `commandments update` — the one
 * command a consumer (or Composer, via the lifecycle scripts) ever runs to stay
 * current. It is idempotent and self-perpetuating:
 *
 *   1. ensures the consumer's composer.json runs `commandments update` on every
 *      `post-update-cmd` AND `post-install-cmd` (so the wiring installs itself the
 *      first time it runs and survives thereafter — no composer-plugin, no
 *      allow-plugins prompt);
 *   2. runs `sync --after=previous`, which already re-registers new prophets,
 *      re-asserts the .gitignore block, re-enters the active profile (regenerating
 *      its hooks) and refreshes scaffold + skills.
 *
 * Multiple small installers do the work; this is the one place that composes them.
 */
final class CommandmentsUpdater
{
    /** What the composer lifecycle scripts invoke — the standalone bin works for Laravel and non-Laravel alike. */
    public const COMPOSER_COMMAND = '@php vendor/bin/commandments update';

    private const LIFECYCLE_EVENTS = ['post-update-cmd', 'post-install-cmd'];

    /**
     * Run the full update: wire the composer scripts, then sync. `$runSync` is the
     * caller's sync invocation (artisan `$this->call(...)` vs the standalone
     * Application) and returns its exit code.
     *
     * @param  callable(): int  $runSync
     * @param  callable(string): void  $emit
     * @param  callable(string): void  $error
     */
    public static function run(string $basePath, callable $runSync, callable $emit, callable $error): int
    {
        self::ensureComposerScripts($basePath, $emit, $error);

        return $runSync();
    }

    /**
     * Idempotently register `commandments update` on both Composer lifecycle events
     * in the consumer's composer.json. Safe-failing: a missing/locked/malformed
     * composer.json is reported, never fatal.
     *
     * @param  callable(string): void  $emit
     * @param  callable(string): void  $error
     */
    public static function ensureComposerScripts(string $basePath, callable $emit, callable $error): void
    {
        $installer = new ComposerScriptInstaller();
        $path = rtrim($basePath, '/') . '/composer.json';

        foreach (self::LIFECYCLE_EVENTS as $event) {
            $status = $installer->install($path, $event, self::COMPOSER_COMMAND);

            match ($status) {
                ComposerScriptInstaller::STATUS_INSTALLED => $emit("Wired `commandments update` into composer.json {$event}"),
                ComposerScriptInstaller::STATUS_ALREADY_PRESENT => null,
                ComposerScriptInstaller::STATUS_MISSING_FILE => $emit('No composer.json found — skipping composer-script wiring.'),
                ComposerScriptInstaller::STATUS_INVALID_JSON => $error('composer.json is not valid JSON — add the `commandments update` scripts manually.'),
                ComposerScriptInstaller::STATUS_WRITE_FAILED => $error('Failed to write composer.json — check permissions.'),
            };
        }
    }
}
