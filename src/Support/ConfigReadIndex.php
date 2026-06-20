<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/**
 * The declared config KEY TREE of a project — every dotted path a `config('...')`
 * read could legitimately target. The forward-flow sibling of {@see ConfigMapIndex}
 * (which indexes config MAPS): this indexes every node and leaf path.
 *
 * A config file is a PHP file that `return`s an array (the `config/*.php` path
 * convention); the file's basename is the top-level segment. `config/services.php`
 * returning `['stripe' => ['key' => env('X')]]` declares the paths `services`,
 * `services.stripe`, `services.stripe.key`. Backs the config-flow prophets (#1/#5/
 * #11) so a read of an UNDECLARED path under an OWNED config namespace (a typo /
 * removed key) is detectable.
 *
 * GENERIC + standalone (config/ is OUTSIDE the scanned scroll, so this scans it
 * directly, walking up to the composer.json+config/ root exactly like
 * {@see ConfigMapIndex}). Pure config-array SHAPE — no Laravel, no `config()`
 * helper, no key-name lists. env()-backed leaves are unresolvable but their KEY is
 * what matters.
 */
final class ConfigReadIndex
{
    /** @var array<string, self> */
    private static array $cache = [];

    /**
     * @param  array<string, true>  $paths      every declared dotted path (nodes + leaves), lowercased
     * @param  array<string, true>  $topLevels  the parsed config-file basenames (owned namespaces), lowercased
     * @param  array<string, true>  $envLeaves  leaf paths whose value is an `env(...)` call (untyped at runtime), lowercased
     */
    private function __construct(
        private readonly array $paths,
        private readonly array $topLevels,
        private readonly array $envLeaves = [],
    ) {}

    /**
     * The declared config key tree for the project owning $filePath — located by
     * walking up to the nearest directory holding BOTH `composer.json` and `config/`.
     */
    public static function forFile(string $filePath): self
    {
        $configDir = self::locateConfigDir($filePath);

        if ($configDir === null) {
            return new self([], [], []);
        }

        return self::$cache[$configDir] ??= self::parseDir($configDir);
    }

    public static function flush(): void
    {
        self::$cache = [];
    }

    /** Whether the project declares no config at all (no config dir / empty). */
    public function isEmpty(): bool
    {
        return $this->topLevels === [];
    }

    /** Whether $segment is a parsed config file (an OWNED config namespace, not framework/vendor). */
    public function ownsTopLevel(string $segment): bool
    {
        return isset($this->topLevels[strtolower($segment)]);
    }

    /** Whether the exact dotted path is a declared node or leaf in the tree. */
    public function hasPath(string $dottedPath): bool
    {
        return isset($this->paths[strtolower($dottedPath)]);
    }

    /**
     * Whether the leaf at this path is `env(...)`-backed — its runtime value is
     * therefore an untyped string (env() casts only true/false/null/empty), so reading
     * it where a typed value is expected without a cast is a type mismatch.
     */
    public function isEnvBacked(string $dottedPath): bool
    {
        return isset($this->envLeaves[strtolower($dottedPath)]);
    }

    /**
     * Declared paths that share the read's parent — the candidate corrections for a
     * near-miss (`assistnt` ↔ `assistant`). Returns a few, sorted.
     *
     * @return list<string>
     */
    public function siblingsOf(string $dottedPath): array
    {
        $parent = strtolower(substr($dottedPath, 0, (int) strrpos($dottedPath, '.')));

        $siblings = [];
        foreach (array_keys($this->paths) as $path) {
            if ($parent !== '' && str_starts_with($path, $parent . '.') && ! str_contains(substr($path, strlen($parent) + 1), '.')) {
                $siblings[$path] = true;
            }
        }

        $names = array_keys($siblings);
        sort($names);

        return array_slice($names, 0, 6);
    }

    private static function locateConfigDir(string $filePath): ?string
    {
        $dir = \dirname($filePath);
        $previous = '';

        while ($dir !== $previous && $dir !== '' && $dir !== '.') {
            if (is_dir($dir . '/config') && is_file($dir . '/composer.json')) {
                return $dir . '/config';
            }

            $previous = $dir;
            $dir = \dirname($dir);
        }

        return null;
    }

    private static function parseDir(string $configDir): self
    {
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $finder = new NodeFinder;
        $paths = [];
        $topLevels = [];
        $envLeaves = [];

        foreach (glob($configDir . '/*.php') ?: [] as $file) {
            try {
                $ast = $parser->parse((string) file_get_contents($file));
            } catch (\Throwable) {
                continue;
            }

            if ($ast === null) {
                continue;
            }

            $return = $finder->findFirstInstanceOf($ast, Node\Stmt\Return_::class);

            if (! $return instanceof Node\Stmt\Return_ || ! $return->expr instanceof Node\Expr\Array_) {
                continue;
            }

            $base = basename($file, '.php');
            $topLevels[strtolower($base)] = true;
            self::collect($return->expr, $base, $paths, $envLeaves);
        }

        return new self($paths, $topLevels, $envLeaves);
    }

    /**
     * @param  array<string, true>  $paths
     * @param  array<string, true>  $envLeaves
     */
    private static function collect(Node\Expr\Array_ $array, string $prefix, array &$paths, array &$envLeaves): void
    {
        foreach ($array->items as $item) {
            if (! $item instanceof Node\Expr\ArrayItem || ! $item->key instanceof Node\Scalar\String_) {
                continue;
            }

            $path = $prefix . '.' . $item->key->value;
            $paths[strtolower($path)] = true;

            if ($item->value instanceof Node\Expr\Array_) {
                self::collect($item->value, $path, $paths, $envLeaves);
            } elseif (self::isEnvCall($item->value)) {
                $envLeaves[strtolower($path)] = true;
            }
        }
    }

    /** Whether a leaf value is an `env('X')` / `env('X', $default)` call. */
    private static function isEnvCall(Node $value): bool
    {
        return $value instanceof Node\Expr\FuncCall
            && $value->name instanceof Node\Name
            && strtolower($value->name->toString()) === 'env';
    }
}
