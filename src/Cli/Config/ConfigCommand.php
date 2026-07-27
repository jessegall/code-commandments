<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Config;

use JesseGall\CodeCommandments\Workspace;

use Composer\InstalledVersions;
use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Detectors\Catalog as DetectorCatalog;
use JesseGall\CodeCommandments\Packages\Catalog as PackageCatalog;
use JesseGall\CodeCommandments\Skills\Catalog as SkillCatalog;

use JesseGall\CodeCommandments\Cli\Judge\SourceRoots;
use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Help\HelpScreen;
use JesseGall\CodeCommandments\Cli\Input;
/**
 * Inspects and manages `.commandments/config.php`; `config` shows effective configuration,
 * `reindex` refreshes source roots from composer.json.
 */
final class ConfigCommand implements Command
{
    public function names(): array
    {
        return ['config'];
    }

    public function help(): Help
    {
        return Help::of('Inspect and manage .commandments/config.php — what is configured, and what is actually running.')
            ->form('config', 'the effective configuration: source roots, detectors running vs available, packages, skills')
            ->form('config reindex', "re-detect the source roots from composer.json and rewrite the config's paths()");
    }

    public function run(Input $input): int
    {
        return match ($input->firstArgument()) {
            null => $this->about(),
            'reindex' => $this->reindex(),
            default => $this->usage($input->firstArgument()),
        };
    }

    private function about(): int
    {
        $root = getcwd() ?: '.';
        $config = Config::load($root);
        $effective = $config->apply(DetectorCatalog::backend(), DetectorCatalog::frontend());

        $roots = $config->sourceRoots() !== [] ? $config->sourceRoots() : new SourceRoots()->detect($root);
        $file = Workspace::config($root);

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

        new ConfigScribe(Workspace::config($root))->rewritePaths($roots);

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
        return HelpScreen::usage($this, "Unknown subcommand '{$subcommand}'.");
    }
}
