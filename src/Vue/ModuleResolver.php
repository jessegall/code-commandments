<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

/**
 * Turns an import specifier into a real file on disk — the backbone of any whole-program
 * trace (the composable trace, and the entry-point component graph). It resolves the three
 * shapes a bundler does:
 *
 *   - **relative** — `./useX`, `../../composables/useX`, against the importing file;
 *   - **aliased** — `@app/composables/useX`, against the project's alias map (longest
 *     prefix wins, so `@app/ui` beats `@app`);
 *   - **barrel / extensionless** — trying `.ts`/`.tsx`/`.vue`/`.js` and an `index.*` folder
 *     entry, exactly as a bundler's module resolution would.
 *
 * A BARE specifier (`vue`, `lodash`) resolves to null — those live in `node_modules` and are
 * out of scope. The alias map is supplied (discovered once from the project config); this
 * class is pure path logic over it, so it is unit-testable without a real project.
 */
final class ModuleResolver
{
    private const array EXTENSIONS = ['.ts', '.tsx', '.vue', '.js', '/index.ts', '/index.tsx', '/index.vue', '/index.js'];

    /** Markers whose nearest ancestor directory is the project root. */
    private const array ROOT_MARKERS = ['vite.config.ts', 'vite.config.js', 'vite.config.mjs', 'vite.config.mts', 'package.json'];

    /** @var array<string, self>  one resolver per project root, aliases discovered once */
    private static array $byRoot = [];

    /** @var list<array{prefix: string, dir: string}>  longest prefix first */
    private array $aliases;

    /**
     * @param  array<string, string>  $aliases  specifier prefix => absolute directory
     */
    public function __construct(array $aliases = [])
    {
        $entries = [];

        foreach ($aliases as $prefix => $dir) {
            $entries[] = ['prefix' => $prefix, 'dir' => rtrim($dir, '/')];
        }

        usort($entries, static fn (array $a, array $b): int => strlen($b['prefix']) <=> strlen($a['prefix']));

        $this->aliases = $entries;
    }

    /**
     * The resolver for the project a file belongs to — its root is the nearest ancestor with
     * a Vite config / `package.json`, and its aliases are discovered from that config once and
     * cached. The entry point for tracing imports across a real codebase.
     */
    public static function forFile(string $file): self
    {
        $root = self::projectRoot(dirname($file));

        return self::$byRoot[$root] ??= new self(ViteAliases::discover($root));
    }

    private static function projectRoot(string $dir): string
    {
        while (true) {
            foreach (self::ROOT_MARKERS as $marker) {
                if (is_file($dir . '/' . $marker)) {
                    return $dir;
                }
            }

            $parent = dirname($dir);

            if ($parent === $dir) {
                return $dir; // filesystem root — no project markers, resolve relatives only
            }

            $dir = $parent;
        }
    }

    /**
     * The absolute file a specifier names when imported from $fromFile, or null when it
     * can't be resolved (a bare/node_modules import, or a file that isn't there).
     */
    public function resolve(string $fromFile, string $specifier): ?string
    {
        $base = $this->base($fromFile, $specifier);

        return $base === null ? null : $this->existing($base);
    }

    /**
     * The un-extensioned path a specifier points at — a relative path joined to the importing
     * file's directory, or an alias's directory plus the remainder. Null for a bare import.
     */
    private function base(string $fromFile, string $specifier): ?string
    {
        if ($specifier === '') {
            return null;
        }

        if ($specifier[0] === '.') {
            return dirname($fromFile) . '/' . $specifier;
        }

        foreach ($this->aliases as $alias) {
            if ($specifier === $alias['prefix']) {
                return $alias['dir'];
            }

            if (str_starts_with($specifier, $alias['prefix'] . '/')) {
                return $alias['dir'] . substr($specifier, strlen($alias['prefix']));
            }
        }

        return null;
    }

    /**
     * The first real file for a base path, trying each module extension and folder index a
     * bundler would. Null when none exists.
     */
    private function existing(string $base): ?string
    {
        foreach (self::EXTENSIONS as $extension) {
            $path = realpath($base . $extension);

            if ($path !== false && is_file($path)) {
                return $path;
            }
        }

        $direct = realpath($base);

        return $direct !== false && is_file($direct) ? $direct : null;
    }
}
