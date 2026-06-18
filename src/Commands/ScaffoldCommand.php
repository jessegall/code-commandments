<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Support\Scaffolding\ScaffoldGenerator;
use JesseGall\CodeCommandments\Support\Scaffolding\ScaffoldReporter;

/**
 * Generate the support classes the prophets recommend into the app's
 * configured namespace.
 */
class ScaffoldCommand extends Command
{
    protected $signature = 'commandments:scaffold
        {--force : Overwrite existing support classes}
        {--auto : Refresh only when scaffold.auto_refresh is enabled (used by the session-start hook); otherwise do nothing}';

    protected $description = 'Generate recommended support classes (Option, FromArrayOnly, …) into your namespace';

    public function handle(): int
    {
        $config = config('commandments.scaffold', []);

        // Auto-refresh implies force (the files are auto-managed) and stamps the
        // generated classes with a loud do-not-edit banner.
        $autoRefresh = (bool) ($config['auto_refresh'] ?? false);

        // The `--auto` hook is a no-op unless auto-refresh is on.
        if ((bool) $this->option('auto') && ! $autoRefresh) {
            return self::SUCCESS;
        }

        $namespace = $config['namespace'] ?? 'App\\Support';
        $path = $config['path'] ?? app_path('Support');
        $except = $config['except'] ?? [];

        $force = $autoRefresh || (bool) $this->option('force');

        $results = ScaffoldGenerator::packaged()
            ->generate($namespace, $path, $force, $except, $autoRefresh);

        $created = ScaffoldReporter::report($results, fn (string $line) => $this->line($line));

        $this->info($created > 0
            ? "Generated {$created} support class(es) into {$namespace}."
            : 'All support classes already present — nothing to generate.');

        return self::SUCCESS;
    }
}
