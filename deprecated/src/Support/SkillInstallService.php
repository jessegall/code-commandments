<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\CodeCommandments\Support\Skills\SkillInstaller;
use JesseGall\CodeCommandments\Support\Skills\SkillReporter;

/**
 * The shared logic behind `install-skills` — one implementation the artisan and
 * standalone commands both call. The commands are thin adapters: resolve config +
 * the skills root, then call this.
 */
final class SkillInstallService
{
    /**
     * Install the packaged skills into `$skillsRoot`, emitting per-file + summary
     * lines. A `--auto` invocation is a no-op unless `skills.auto_refresh` is on
     * (auto-refresh implies force + the do-not-edit banner). Skill examples are
     * rewritten to the consumer's `scaffold.namespace` so they match generated code.
     *
     * @param  array<string, mixed>  $config  the loaded `commandments` config
     * @param  callable(string): void  $emit
     */
    public static function install(array $config, string $skillsRoot, bool $optAuto, bool $optForce, callable $emit): void
    {
        $skills = $config['skills'] ?? [];
        $autoRefresh = (bool) ($skills['auto_refresh'] ?? false);

        // The session-start `--auto` hook does nothing unless auto-refresh is on.
        if ($optAuto && ! $autoRefresh) {
            return;
        }

        $results = SkillInstaller::packaged()->install(
            $config['scaffold']['namespace'] ?? 'App\\Support',
            $skillsRoot,
            $autoRefresh || $optForce,
            $skills['except'] ?? [],
            $autoRefresh,
        );

        $installed = SkillReporter::report($results, $emit);

        $emit($installed > 0
            ? "Installed {$installed} skill(s) into .claude/skills/."
            : 'All skills already present — nothing to install.');
    }
}
