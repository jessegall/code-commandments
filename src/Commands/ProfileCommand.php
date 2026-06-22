<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Support\Profiles\ProfileService;

/**
 * Show, list, or switch the active code-commandments profile. Thin adapter over
 * {@see ProfileService}.
 */
class ProfileCommand extends Command
{
    protected $signature = 'commandments:profile
        {name? : Profile to switch to, or "list" to see them all}
        {--brief : Print the active profile briefing (session-start hook)}
        {--drift-check : Re-brief only when the profile changed (per-turn hook)}';

    protected $description = 'Show, list, or switch the active code-commandments profile (disabled/grind/phased/sins-only)';

    public function handle(): int
    {
        $config = config('commandments', []);
        $service = new ProfileService(base_path(), is_array($config) ? $config : []);

        $emit = fn (string $line) => $this->output->writeln($line);
        $error = fn (string $line) => $this->error($line);

        if ($this->option('brief')) {
            $service->brief($emit);

            return self::SUCCESS;
        }

        if ($this->option('drift-check')) {
            $service->driftCheck($emit);

            return self::SUCCESS;
        }

        $name = $this->argument('name');

        if ($name === null || $name === 'show') {
            $service->show($emit);

            return self::SUCCESS;
        }

        if ($name === 'list') {
            $service->list($emit);

            return self::SUCCESS;
        }

        return $service->switch($name, $emit, $error) === ProfileService::SUCCESS ? self::SUCCESS : self::FAILURE;
    }
}
