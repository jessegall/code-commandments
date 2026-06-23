<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\CodeCommandments\Support\Profiles\ProfileKeepGoingHook;
use JesseGall\CodeCommandments\Support\Profiles\ProfileService;
use JesseGall\CodeCommandments\Support\Scaffolding\ScaffoldGenerator;
use JesseGall\CodeCommandments\Support\Scaffolding\ScaffoldReporter;
use JesseGall\CodeCommandments\Support\Skills\SkillInstaller;
use JesseGall\CodeCommandments\Support\Skills\SkillReporter;

/**
 * The shared logic behind `sync`'s auto-refresh side-effects — scaffold, skills,
 * .gitignore, the handoff helper, the plan-loop scripts, the settings.json hook
 * wiring, and the CLAUDE.md section. ONE implementation both the artisan
 * {@see \JesseGall\CodeCommandments\Commands\SyncCommand} and the standalone
 * {@see \JesseGall\CodeCommandments\Console\SyncConsoleCommand} call, so there is
 * no parallel logic to keep in sync (the two had already drifted — that is what
 * this collapses). The commands are thin adapters: they load config, call this,
 * and print the returned lines.
 *
 * Framework-agnostic: it takes the loaded `commandments` config array + the base
 * path and returns human-readable status lines (empty when a step was a no-op),
 * delegating every effect to the existing installers.
 */
final class SyncService
{
    /**
     * Run every auto-refresh side-effect a sync performs and return the status
     * lines to print (in order). A no-op step contributes nothing.
     *
     * @param  array<string, mixed>  $config  the loaded `commandments` config
     * @return list<string>
     */
    public static function refreshSideEffects(string $basePath, array $config): array
    {
        $lines = [];
        $collect = static function (string $line) use (&$lines): void {
            $lines[] = $line;
        };

        self::refreshScaffold($basePath, $config, $collect);
        self::refreshSkills($basePath, $config, $collect);
        self::refreshGitignore($basePath, $config, $collect);
        self::refreshHookScripts($basePath, $config, $collect);
        self::reassertActiveProfile($basePath, $config, $collect);

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  callable(string): void  $emit
     */
    private static function refreshScaffold(string $basePath, array $config, callable $emit): void
    {
        $scaffold = $config['scaffold'] ?? [];

        if (($scaffold['auto'] ?? true) === false) {
            return;
        }

        $results = ScaffoldGenerator::packaged()->generate(
            $scaffold['namespace'] ?? 'App\\Support',
            $scaffold['path'] ?? ($basePath . '/app/Support'),
            false,
            $scaffold['except'] ?? [],
        );

        $created = ScaffoldReporter::report($results, $emit);

        if ($created > 0) {
            $emit("Generated {$created} new support class(es).");
        }
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  callable(string): void  $emit
     */
    private static function refreshSkills(string $basePath, array $config, callable $emit): void
    {
        $skills = $config['skills'] ?? [];

        if (($skills['auto'] ?? true) === false) {
            return;
        }

        $autoRefresh = (bool) ($skills['auto_refresh'] ?? false);

        $results = SkillInstaller::packaged()->install(
            $config['scaffold']['namespace'] ?? 'App\\Support',
            $basePath . '/.claude/skills',
            $autoRefresh,
            $skills['except'] ?? [],
            $autoRefresh,
        );

        $installed = SkillReporter::report($results, $emit);

        if ($installed > 0) {
            $emit("Installed {$installed} new skill(s).");
        }
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  callable(string): void  $emit
     */
    private static function refreshGitignore(string $basePath, array $config, callable $emit): void
    {
        $ignoreSkills = (bool) ($config['skills']['auto_refresh'] ?? false);

        match ((new GitignoreInstaller())->ensure($basePath, $ignoreSkills)) {
            GitignoreInstaller::STATUS_INSTALLED => $emit('Created .gitignore with code-commandments state entries'),
            GitignoreInstaller::STATUS_APPENDED => $emit('Added code-commandments state entries to .gitignore'),
            GitignoreInstaller::STATUS_UPDATED => $emit('Refreshed code-commandments state entries in .gitignore'),
            GitignoreInstaller::STATUS_WRITE_FAILED => $emit('Failed to write .gitignore — check permissions.'),
            GitignoreInstaller::STATUS_ALREADY_PRESENT => null,
        };
    }

    /**
     * Refresh the on-disk hook scripts: the always-on handoff helpers (only for a
     * consumer that already uses the package's Claude hooks) and the opt-in
     * plan-loop suite (only when enabled).
     *
     * @param  array<string, mixed>  $config
     * @param  callable(string): void  $emit
     */
    private static function refreshHookScripts(string $basePath, array $config, callable $emit): void
    {
        if (is_dir($basePath . '/.claude/hooks')
            && HandoffHelper::install($basePath) === HandoffHelper::STATUS_INSTALLED) {
            $emit('Refreshed the handoff helpers in .claude/hooks/');
        }

        // Keep the profile keep-going Stop hook current (only meaningful once a
        // profile wires it, but refreshing the script is always safe).
        if (is_dir($basePath . '/.claude/hooks')) {
            ProfileKeepGoingHook::install($basePath);
        }

        if (PlanLoopHookSuite::enabled($config)
            && PlanLoopHookSuite::install($basePath) === PlanLoopHookSuite::STATUS_INSTALLED) {
            $emit('Refreshed the plan-loop hook scripts in .claude/hooks/');
        }
    }

    /**
     * Refresh ONLY the active profile's bundle (git hooks, settings.json wiring,
     * CLAUDE.md section) to the current package version — and persist the inferred
     * selection so a legacy consumer becomes an explicit `phased`. A `disabled`
     * consumer is a no-op that removes nothing; this is what stops a package update
     * from force-feeding hooks onto every consumer.
     *
     * @param  array<string, mixed>  $config
     * @param  callable(string): void  $emit
     */
    private static function reassertActiveProfile(string $basePath, array $config, callable $emit): void
    {
        (new ProfileService($basePath, $config))->reassert(
            $emit,
            $emit,
        );
    }
}
