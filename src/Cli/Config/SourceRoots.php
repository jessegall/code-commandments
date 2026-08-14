<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Config;

use JesseGall\CodeCommandments\Config;

/**
 * Resolves the source roots a CLI command scans — the ONE resolution `judge` and `repent` share,
 * so neither can scope differently. An explicit path is scanned exactly as given; otherwise the
 * roots come from `config.php`'s {@see Config::paths} declaration. On a fresh project (no roots
 * declared) it auto-detects them from composer.json's PSR-4 map (plus `app`/`src`) and has the
 * {@see ConfigScribe} write them into `config.php` — so the config, not a side file, is the source.
 */
final class SourceRoots
{
    /**
     * PSR-4 can map a namespace at a non-source tree (Laravel autoloads `Database\Factories` /
     * `Database\Seeders`); those are scaffolding, not the code under review, so they're left out.
     */
    private const array NOT_SOURCE = ['database', 'tests', 'test'];

    /**
     * The absolute source roots to scan under $root: an explicit path as given, else the config's
     * declared paths — auto-detected and scaffolded into `config.php` the first time.
     *
     * @return list<string>
     */
    public function resolve(string $root, bool $pathGiven): array
    {
        if ($pathGiven) {
            return [rtrim($root, '/')];
        }

        return $this->declared($root);
    }

    /**
     * The roots to PARSE for a run targeting $path — every declared source root of the project $path
     * lives in, plus $path itself.
     *
     * Wider than {@see resolve}, and deliberately: a cross-file question ("does anything extend this
     * class?", "who calls this?") is only sound against the whole project. Answered against one root it
     * says `no` merely because the other half of the tree was never read, and a rewrite that seals a
     * class on that answer emits PHP that will not load (#483). What may be WRITTEN stays narrow —
     * {@see \JesseGall\CodeCommandments\Cli\Scope\PathScope} keeps that promise.
     *
     * @return list<string>
     */
    public function toParse(string $project, string $path): array
    {
        // Both sides as realpaths, or a relative `resources/js` reads as outside an absolute root that
        // already contains it — and the same files, reached by two spellings, would be PARSED TWICE.
        $target = rtrim(realpath($path) ?: $path, '/');
        $home = rtrim(realpath($project) ?: $project, '/');

        // A path OUTSIDE the project is its own world. Widening to this project's roots would parse a
        // tree that has nothing to do with the target — the cost of every file in it, for no answer.
        if ($target !== $home && ! str_starts_with($target, $home . '/')) {
            return [$target];
        }

        $roots = $this->declared($project);

        foreach ($roots as $root) {
            $real = rtrim(realpath($root) ?: $root, '/');

            if ($target === $real || str_starts_with($target, $real . '/')) {
                return $roots;
            }
        }

        return [...$roots, $target];
    }

    /**
     * The project's declared source roots, absolute — detected and scaffolded into `config.php` the
     * first time.
     *
     * @return list<string>
     */
    private function declared(string $root): array
    {
        $declared = Config::load($root)->sourceRoots();

        if ($declared === []) {
            $declared = $this->detect($root);
            $scribe = ConfigScribe::inProject($root);

            if (! $scribe->scaffold($declared)) {
                $scribe->ensurePaths($declared);
            }
        }

        return $this->absolute($root, $declared);
    }

    /**
     * Infer the source roots: every PSR-4 autoload dir that isn't scaffolding, plus `app`/`src` if
     * present. Falls back to the project root when nothing is detected, so a run is never empty.
     * Public so `commandments paths` can regenerate the config's declaration from a fresh detection.
     *
     * @return list<string>
     */
    public function detect(string $root): array
    {
        $roots = [];

        foreach ($this->psr4Dirs($root) as $dir) {
            $top = explode('/', $dir)[0];

            if (! in_array($top, self::NOT_SOURCE, true) && ! str_starts_with($top, '.')) {
                $roots[$dir] = true;
            }
        }

        foreach (['app', 'src'] as $convention) {
            if (is_dir($root . '/' . $convention)) {
                $roots[$convention] = true;
            }
        }

        $roots = array_values(array_filter(array_keys($roots), static fn (string $dir): bool => is_dir($root . '/' . $dir)));
        sort($roots);

        return $roots === [] ? ['.'] : $roots;
    }

    /**
     * The PSR-4 autoload directories declared in composer.json (a namespace can map to several),
     * normalised to trimmed relative paths.
     *
     * @return list<string>
     */
    private function psr4Dirs(string $root): array
    {
        $composer = $root . '/composer.json';

        if (! is_file($composer)) {
            return [];
        }

        $json = json_decode((string) @file_get_contents($composer), true);
        $map = $json['autoload']['psr-4'] ?? [];

        $dirs = [];

        foreach (is_array($map) ? $map : [] as $paths) {
            foreach ((array) $paths as $dir) {
                $dir = trim((string) $dir, '/');

                if ($dir !== '') {
                    $dirs[] = $dir;
                }
            }
        }

        return $dirs;
    }

    /**
     * @param  list<string>  $roots
     * @return list<string>
     */
    private function absolute(string $root, array $roots): array
    {
        $root = rtrim($root, '/');

        return array_values(array_map(static fn (string $dir): string => $dir === '.' ? $root : "{$root}/{$dir}", $roots));
    }
}
