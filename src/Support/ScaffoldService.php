<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\CodeCommandments\Support\Scaffolding\ScaffoldGenerator;
use JesseGall\CodeCommandments\Support\Scaffolding\ScaffoldReporter;

/**
 * The shared logic behind `scaffold` — one implementation the artisan and
 * standalone commands both call, so the auto/force/banner rules can't drift. The
 * commands are thin adapters: resolve config + the default path, then call this.
 */
final class ScaffoldService
{
    /**
     * Generate the recommended support classes, emitting per-file + summary lines.
     * A `--auto` invocation is a no-op unless `scaffold.auto_refresh` is on
     * (auto-refresh implies force + the do-not-edit banner).
     *
     * @param  array<string, mixed>  $scaffold  the `scaffold` config section
     * @param  callable(string): void  $emit
     */
    public static function generate(array $scaffold, string $defaultPath, bool $optAuto, bool $optForce, callable $emit): void
    {
        $autoRefresh = (bool) ($scaffold['auto_refresh'] ?? false);

        // The session-start `--auto` hook does nothing unless auto-refresh is on.
        if ($optAuto && ! $autoRefresh) {
            return;
        }

        $namespace = $scaffold['namespace'] ?? 'App\\Support';

        $results = ScaffoldGenerator::packaged()->generate(
            $namespace,
            $scaffold['path'] ?? $defaultPath,
            $autoRefresh || $optForce,
            $scaffold['except'] ?? [],
            $autoRefresh,
        );

        $created = ScaffoldReporter::report($results, $emit);

        $emit($created > 0
            ? "Generated {$created} support class(es) into {$namespace}."
            : 'All support classes already present — nothing to generate.');
    }
}
