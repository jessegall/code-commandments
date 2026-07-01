<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use Composer\InstalledVersions;
use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Detectors\Catalog as DetectorCatalog;
use JesseGall\CodeCommandments\Packages\Catalog as PackageCatalog;
use JesseGall\CodeCommandments\Skills\Catalog as SkillCatalog;

/**
 * `commandments config [reindex]` — inspect and manage `.commandments/config.php`.
 *
 *   config          — print a human-friendly summary of the effective configuration (scan roots,
 *                     how many detectors run, custom detectors/packages) — a `php artisan about`
 *                     for this project's scope.
 *   config reindex  — re-detect the source roots from composer.json (PSR-4 + `app`/`src`) and
 *                     OVERWRITE `$config->paths(...)` with the fresh list. Everything else in the
 *                     config (disable/detector/…) is left untouched.
 */
final class ConfigCommand
{
    public function run(array $args): int
    {
        return match ($this->firstArgument($args)) {
            null => $this->about(),
            'reindex' => $this->reindex(),
            default => $this->usage($this->firstArgument($args)),
        };
    }

    private function about(): int
    {
        $root = getcwd() ?: '.';
        $config = Config::load($root);
        $effective = $config->apply(DetectorCatalog::backend(), DetectorCatalog::frontend());

        $roots = $config->sourceRoots() !== [] ? $config->sourceRoots() : new SourceRoots()->detect($root);
        $file = $root . '/.commandments/config.php';

        echo "\n  \033[1mcode-commandments\033[0m  " . $this->version() . "\n\n";

        $this->row('Config', is_file($file) ? '.commandments/config.php' : '.commandments/config.php (not yet written)');
        $this->row('Source roots', implode(', ', $roots));
        $this->row('Backend detectors', count($effective['backend']) . ' running  ·  ' . count(DetectorCatalog::backend()) . ' available');
        $this->row('Frontend detectors', count($effective['frontend']) . ' running  ·  ' . count(DetectorCatalog::frontend()) . ' available');
        $this->row('Custom detectors', (string) count($config->registeredDetectors()));
        $this->row('Exemption packages', count(PackageCatalog::all()) . ' built-in  ·  ' . count($config->packages()) . ' registered');
        $this->row('Skills', (string) count(SkillCatalog::all()));

        echo "\n  \033[2mRun `commandments config reindex` to re-detect the source roots.\033[0m\n\n";

        return 0;
    }

    private function reindex(): int
    {
        $root = getcwd() ?: '.';
        $roots = new SourceRoots()->detect($root);

        new ConfigScribe($root . '/.commandments/config.php')->rewritePaths($roots);

        echo "\033[32m✓ Reindexed " . count($roots) . " source root(s) into .commandments/config.php:\033[0m " . implode(', ', $roots) . "\n";

        return 0;
    }

    private function row(string $label, string $value): void
    {
        echo "  \033[36m" . str_pad($label . ' ', 22, '.') . "\033[0m {$value}\n";
    }

    private function version(): string
    {
        if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('jessegall/code-commandments')) {
            return (string) InstalledVersions::getPrettyVersion('jessegall/code-commandments');
        }

        return 'dev';
    }

    private function usage(?string $subcommand): int
    {
        fwrite(STDERR, "Unknown subcommand '{$subcommand}'. Usage: commandments config [reindex]\n");

        return 2;
    }

    private function firstArgument(array $args): ?string
    {
        foreach ($args as $arg) {
            if (! str_starts_with($arg, '--')) {
                return $arg;
            }
        }

        return null;
    }
}
