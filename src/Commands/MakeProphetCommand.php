<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Summon a new prophet into existence.
 *
 * Creates a new commandment validator (prophet) class.
 */
class MakeProphetCommand extends Command
{
    protected $signature = 'make:prophet
        {name : The name of the prophet (e.g., NoMagicNumbers)}
        {--scroll=backend : The scroll this prophet belongs to (backend or frontend)}
        {--type=php : The type of files this prophet judges (php or frontend)}
        {--repentable : Whether this prophet can auto-fix sins}
        {--confession : Whether this prophet requires manual review}';

    protected $description = 'Summon a new prophet into existence';

    public function handle(Filesystem $files): int
    {
        $name = $this->argument('name');
        $scroll = $this->option('scroll');
        $type = $this->option('type');
        $repentable = $this->option('repentable');
        $confession = $this->option('confession');

        // Ensure name ends with Prophet
        if (!Str::endsWith($name, 'Prophet')) {
            $name .= 'Prophet';
        }

        // Determine the stub to use
        $stubName = $type === 'frontend' ? 'prophet-frontend.php.stub' : 'prophet-php.php.stub';
        $stubPath = dirname(__DIR__, 2) . '/stubs/' . $stubName;

        if (!$files->exists($stubPath)) {
            $this->error("Stub file not found: {$stubPath}");
            return self::FAILURE;
        }

        // Determine output path
        $scrollDir = ucfirst($scroll);
        $outputDir = app_path("Prophets/{$scrollDir}");

        if (!$files->isDirectory($outputDir)) {
            $files->makeDirectory($outputDir, 0755, true);
        }

        $outputPath = "{$outputDir}/{$name}.php";

        if ($files->exists($outputPath)) {
            $this->error("Prophet already exists: {$outputPath}");
            return self::FAILURE;
        }

        // Read stub and replace placeholders
        $stub = $files->get($stubPath);
        $namespace = "App\\Prophets\\{$scrollDir}";

        $replacements = [
            '{{ namespace }}' => $namespace,
            '{{ class }}' => $name,
            '{{ description }}' => $this->generateDescription($name),
            '{{ requiresConfession }}' => $confession ? 'true' : 'false',
        ];

        $content = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $stub
        );

        // Add SinRepenter interface if repentable
        if ($repentable) {
            $content = str_replace(
                'use JesseGall\\CodeCommandments\\Results\\Judgment;',
                "use JesseGall\\CodeCommandments\\Results\\Judgment;\nuse JesseGall\\CodeCommandments\\Contracts\\SinRepenter;\nuse JesseGall\\CodeCommandments\\Results\\RepentanceResult;",
                $content
            );
            $content = str_replace(
                "extends PhpCommandment\n{",
                "extends PhpCommandment implements SinRepenter\n{",
                $content
            );
            $content = str_replace(
                "extends FrontendCommandment\n{",
                "extends FrontendCommandment implements SinRepenter\n{",
                $content
            );
        }

        $files->put($outputPath, $content);

        $this->output->writeln('<fg=green>');
        $this->output->writeln('  ╔═══════════════════════════════════════════════════════════╗');
        $this->output->writeln('  ║           A NEW PROPHET HAS BEEN SUMMONED                 ║');
        $this->output->writeln('  ╚═══════════════════════════════════════════════════════════╝');
        $this->output->writeln('</>');
        $this->newLine();

        $this->info("  Prophet created: {$name}");
        $this->line("  <fg=gray>Location: {$outputPath}</>");
        $this->newLine();
        $this->line("  <fg=yellow>Remember to register your prophet in config/commandments.php:</>");
        $this->line("  <fg=gray>'{$scroll}' => [");
        $this->line("      'prophets' => [");
        $this->line("          \\{$namespace}\\{$name}::class,");
        $this->line("      ],");
        $this->line("  ]</>");

        return self::SUCCESS;
    }

    protected function generateDescription(string $name): string
    {
        // Convert ThouShaltNotUseDdProphet to "Thou shalt not use dd"
        $name = Str::replaceLast('Prophet', '', $name);
        $name = Str::snake($name, ' ');
        $name = ucfirst($name);

        // Convert common patterns
        $name = str_replace(' no ', ' not ', $name);
        $name = str_replace('Thou shalt not', 'Thou shalt not', $name);

        if (!Str::startsWith($name, 'Thou')) {
            $name = 'Thou shalt ' . lcfirst($name);
        }

        return $name;
    }
}
