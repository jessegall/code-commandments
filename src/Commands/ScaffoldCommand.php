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
        {--force : Overwrite existing support classes}';

    protected $description = 'Generate recommended support classes (Option, FromArrayOnly, …) into your namespace';

    public function handle(): int
    {
        $config = config('commandments.scaffold', []);

        $namespace = $config['namespace'] ?? 'App\\Support';
        $path = $config['path'] ?? app_path('Support');
        $except = $config['except'] ?? [];

        $results = ScaffoldGenerator::packaged()
            ->generate($namespace, $path, (bool) $this->option('force'), $except);

        $created = ScaffoldReporter::report($results, fn (string $line) => $this->line($line));

        $this->info($created > 0
            ? "Generated {$created} support class(es) into {$namespace}."
            : 'All support classes already present — nothing to generate.');

        return self::SUCCESS;
    }
}
