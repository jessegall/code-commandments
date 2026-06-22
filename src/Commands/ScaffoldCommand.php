<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Support\ScaffoldService;

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
        ScaffoldService::generate(
            config('commandments.scaffold', []),
            app_path('Support'),
            (bool) $this->option('auto'),
            (bool) $this->option('force'),
            fn (string $line) => $this->line($line),
        );

        return self::SUCCESS;
    }
}
