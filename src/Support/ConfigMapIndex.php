<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use JesseGall\PhpTypes\T_String;

/**
 * The config-value flow resolver (#registry-from-config): indexes the data-driven
 * MAPS a project DECLARES in its config files, so a prophet can ask "is this set
 * the code hardcodes also registered, as data, in config?".
 *
 * A config file is a PHP file that `return`s an array (the `config/*.php`
 * convention); a "map" is a keyed sub-array of >= 2 STRING keys (a registered set
 * — `providers => ['anthropic' => …, 'openai' => …]`), as opposed to a list. Each
 * map is recorded with its dotted path (`<file>.<nested>.<keys>`) and key set.
 *
 * GENERIC BY DESIGN: no Laravel, no `config()` helper, no key-name lists — purely
 * the config-file array SHAPE. `config/` is a path convention (configurable), and
 * `return [array]` is the structural signal. env()-backed leaves are unresolvable
 * statically, but the KEY tree (the declared set) is all this needs.
 */
final class ConfigMapIndex
{
    /** @var array<string, self> */
    private static array $cache = [];

    /**
     * @param  list<array{path: string, keys: list<string>}>  $maps
     */
    private function __construct(private readonly array $maps) {}

    /**
     * The config-map index for the project owning $filePath — located by walking up
     * to the nearest directory that holds BOTH a `composer.json` and a `config/`
     * dir (the project root). Cached per config directory.
     */
    public static function forFile(string $filePath): self
    {
        $configDir = self::locateConfigDir($filePath);

        if ($configDir === null) {
            return new self([]);
        }

        return self::$cache[$configDir] ??= new self(self::parseDir($configDir));
    }

    /** Clear the parse cache (test isolation). */
    public static function flush(): void
    {
        self::$cache = [];
    }

    /**
     * Every declared config map whose key set EXACTLY equals the given token set
     * (order/-case-insensitive) — the strong cross-artifact congruence: the code's
     * hardcoded set IS the config-registered set.
     *
     * @param  list<string>  $tokens
     * @return list<array{path: string, keys: list<string>}>
     */
    public function mapsMatching(array $tokens): array
    {
        $want = self::normalise($tokens);

        if (count($want) < 2) {
            return [];
        }

        return array_values(array_filter(
            $this->maps,
            static fn (array $map): bool => self::normalise($map['keys']) === $want,
        ));
    }

    /**
     * @return list<array{path: string, keys: list<string>}>
     */
    public function maps(): array
    {
        return $this->maps;
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>  unique, lowercased, sorted
     */
    private static function normalise(array $keys): array
    {
        $keys = array_values(array_unique(array_map(
            strtolower(...),
            $keys,
        )));

        sort($keys);

        return $keys;
    }

    private static function locateConfigDir(string $filePath): ?string
    {
        $dir = \dirname($filePath);
        $previous = T_String::empty();

        while ($dir !== $previous && $dir !== '' && $dir !== '.') {
            if (is_dir($dir . '/config') && is_file($dir . '/composer.json')) {
                return $dir . '/config';
            }

            $previous = $dir;
            $dir = \dirname($dir);
        }

        return null;
    }

    /**
     * @return list<array{path: string, keys: list<string>}>
     */
    private static function parseDir(string $configDir): array
    {
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $finder = new NodeFinder;
        $maps = [];

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

            self::collect($return->expr, basename($file, '.php'), $maps);
        }

        return $maps;
    }

    /**
     * @param  list<array{path: string, keys: list<string>}>  $maps
     */
    private static function collect(Node\Expr\Array_ $array, string $path, array &$maps): void
    {
        $keys = [];

        foreach ($array->items as $item) {
            if ($item instanceof Node\Expr\ArrayItem && $item->key instanceof Node\Scalar\String_) {
                $keys[] = $item->key->value;
            }
        }

        // A MAP is >= 2 STRING-keyed entries; a list (no/int keys) is not a registered set.
        if (count($keys) >= 2) {
            $maps[] = ['path' => $path, 'keys' => $keys];
        }

        // Recurse into nested arrays, extending the dotted path through string keys.
        foreach ($array->items as $item) {
            if ($item instanceof Node\Expr\ArrayItem && $item->value instanceof Node\Expr\Array_) {
                $childPath = $item->key instanceof Node\Scalar\String_
                    ? $path . '.' . $item->key->value
                    : $path;

                self::collect($item->value, $childPath, $maps);
            }
        }
    }
}
