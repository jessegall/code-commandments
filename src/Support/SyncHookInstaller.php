<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\PhpTypes\T_String;

/**
 * The shared logic behind `install-sync-hook` — write the git post-merge hook
 * (one runner-detecting body via {@see ClaudeHooksInstaller::postMergeHookScript()}),
 * add the composer post-update-cmd, and record the baseline synced version. One
 * implementation both command variants call; they pass their own composer command
 * (artisan `@php` vs standalone shell) and output sinks.
 */
final class SyncHookInstaller
{
    public const SUCCESS = 0;
    public const FAILURE = 1;

    /**
     * @param  callable(string): void  $emit
     * @param  callable(string): void  $error
     */
    public static function install(string $basePath, bool $force, string $composerCommand, callable $emit, callable $error): int
    {
        $hookPath = rtrim($basePath, '/') . '/.git/hooks/post-merge';
        $gitDir = dirname($hookPath);

        if (! is_dir(dirname($gitDir))) {
            $error('Not a git repository (no .git directory found).');

            return self::FAILURE;
        }

        if (! is_dir($gitDir)) {
            @mkdir($gitDir, 0755, true);
        }

        if (is_file($hookPath) && ! $force) {
            $emit("Hook already exists at {$hookPath}. Re-run with --force to overwrite.");

            return self::SUCCESS;
        }

        if (@file_put_contents($hookPath, ClaudeHooksInstaller::postMergeHookScript() . T_String::NEWLINE) === false) {
            $error("Failed to write {$hookPath}");

            return self::FAILURE;
        }

        @chmod($hookPath, 0755);
        $emit('Installed git post-merge hook at .git/hooks/post-merge');

        self::installComposerScript($basePath, $composerCommand, $emit, $error);
        self::recordBaselineVersion($basePath, $emit, $error);

        $emit('Now every git pull and composer update will run sync automatically.');

        return self::SUCCESS;
    }

    /**
     * @param  callable(string): void  $emit
     * @param  callable(string): void  $error
     */
    private static function installComposerScript(string $basePath, string $command, callable $emit, callable $error): void
    {
        $status = (new ComposerScriptInstaller())->install($basePath . '/composer.json', 'post-update-cmd', $command);

        match ($status) {
            ComposerScriptInstaller::STATUS_INSTALLED => $emit("Added {$command} to composer.json post-update-cmd"),
            ComposerScriptInstaller::STATUS_ALREADY_PRESENT => $emit('composer.json already has the sync script — skipped'),
            ComposerScriptInstaller::STATUS_MISSING_FILE => $emit('No composer.json found — skipping composer-script install.'),
            ComposerScriptInstaller::STATUS_INVALID_JSON => $error('composer.json is not valid JSON — skipping composer-script install. Add the script manually.'),
            ComposerScriptInstaller::STATUS_WRITE_FAILED => $error('Failed to write composer.json — check permissions.'),
        };
    }

    /**
     * @param  callable(string): void  $emit
     * @param  callable(string): void  $error
     */
    private static function recordBaselineVersion(string $basePath, callable $emit, callable $error): void
    {
        $resolver = new VersionResolver();
        $current = $resolver->currentVersion();

        if ($current === null) {
            $error('Could not resolve installed package version (dev install?) — skipping baseline record.');

            return;
        }

        if ($resolver->recordSyncedVersion($basePath, $current)) {
            $emit("Recorded baseline sync version {$current} in .commandments-last-synced");
        } else {
            $error('Failed to write .commandments-last-synced — check permissions.');
        }
    }
}
