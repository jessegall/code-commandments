<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * The canon: the source roots judged as scripture. A `judge` with no explicit path
 * scans these — not the whole project tree, where most of what lives there
 * (migrations, config, compiled views, public assets) isn't backend code under
 * review.
 *
 * The canon is declared in `.commandments/backend.canon` (one root per line, `#`
 * comments). The FIRST time it's needed it's HYDRATED: the source roots are
 * inferred — from `composer.json`'s PSR-4 autoload map, plus the `app`/`src`
 * conventions — and written to that file for the user to keep or edit. So it
 * behaves like a `.gitignore`: generated with a sane default, then yours to tune.
 */
final class Canon
{
    private const string FILE = '.commandments/backend.canon';

    /**
     * PSR-4 can map a namespace at a non-source tree (Laravel autoloads
     * `Database\Factories` / `Database\Seeders`); those are scaffolding, not the
     * backend under review, so they're left out of the seed.
     */
    private const array NOT_SOURCE = ['database', 'tests', 'test'];

    /**
     * Resolve the source roots to scan under $root, reading the canon file or
     * hydrating it on first use.
     */
    public function resolve(string $root): CanonResolution
    {
        $file = rtrim($root, '/') . '/' . self::FILE;

        if (is_file($file)) {
            return new CanonResolution($this->absolute($root, $this->read($file)), false, $file);
        }

        $roots = $this->detect($root);
        $this->write($file, $roots);

        return new CanonResolution($this->absolute($root, $roots), true, $file);
    }

    /**
     * Parse the canon file into relative roots (skip blanks and `#` comments).
     *
     * @return list<string>
     */
    private function read(string $file): array
    {
        $roots = [];

        foreach (explode("\n", (string) @file_get_contents($file)) as $line) {
            $line = trim($line);

            if ($line !== '' && ! str_starts_with($line, '#')) {
                $roots[] = $line;
            }
        }

        return $roots;
    }

    /**
     * Infer the backend source roots: every PSR-4 autoload dir that isn't
     * scaffolding, plus `app`/`src` if present. Falls back to the project root when
     * nothing is detected, so a run is never empty.
     *
     * @return list<string>
     */
    private function detect(string $root): array
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
     * The PSR-4 autoload directories declared in composer.json (a namespace can map
     * to several), normalised to trimmed relative paths.
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
     */
    private function write(string $file, array $roots): void
    {
        @mkdir(dirname($file), 0755, true);

        $body = "# The canon: backend source roots judged as scripture.\n"
            . "# One root per line, relative to the project root. '#' starts a comment.\n"
            . "# Auto-generated on first run (from composer.json PSR-4 + app/src). Edit\n"
            . "# freely: add a line to judge more, delete one to stop judging it.\n\n"
            . implode("\n", $roots) . "\n";

        @file_put_contents($file, $body);
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
