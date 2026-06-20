<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/**
 * The declared translation KEY TREE of a project — every dotted key a `__()` /
 * `trans()` call could legitimately target. The lang-file sibling of
 * {@see ConfigReadIndex}. Standalone (lang/ is outside the scanned scroll): it walks
 * up to the composer.json root, finds `lang/` or `resources/lang/`, and unions the
 * keys across ALL locale subdirectories — a key declared in any locale is "known".
 *
 * A group is a `lang/<locale>/<group>.php` file returning an array; its basename is
 * the top-level segment. `lang/en/auth.php` returning `['failed' => '…']` declares
 * the key `auth.failed`, and "owns" the `auth` group. Backs
 * TranslationKeyCongruenceProphet (#163 #2). Pure array shape — no Laravel.
 */
final class LangKeyIndex
{
    /** @var array<string, self> */
    private static array $cache = [];

    /**
     * @param  array<string, true>  $keys    every declared dotted key (nodes + leaves), lowercased
     * @param  array<string, true>  $groups  the group basenames present in any locale, lowercased
     */
    private function __construct(
        private readonly array $keys,
        private readonly array $groups,
    ) {}

    public static function forFile(string $filePath): self
    {
        $langDir = self::locateLangDir($filePath);

        if ($langDir === null) {
            return new self([], []);
        }

        return self::$cache[$langDir] ??= self::parseDir($langDir);
    }

    public static function flush(): void
    {
        self::$cache = [];
    }

    public function isEmpty(): bool
    {
        return $this->groups === [];
    }

    /** Whether $group is a declared lang group (a `<locale>/<group>.php` file exists). */
    public function ownsGroup(string $group): bool
    {
        return isset($this->groups[strtolower($group)]);
    }

    /** Whether the dotted key is declared in any locale. */
    public function hasKey(string $dottedKey): bool
    {
        return isset($this->keys[strtolower($dottedKey)]);
    }

    private static function locateLangDir(string $filePath): ?string
    {
        $dir = \dirname($filePath);
        $previous = '';

        while ($dir !== $previous && $dir !== '' && $dir !== '.') {
            if (is_file($dir . '/composer.json')) {
                foreach (['lang', 'resources/lang'] as $candidate) {
                    if (is_dir($dir . '/' . $candidate)) {
                        return $dir . '/' . $candidate;
                    }
                }
            }

            $previous = $dir;
            $dir = \dirname($dir);
        }

        return null;
    }

    private static function parseDir(string $langDir): self
    {
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $finder = new NodeFinder;
        $keys = [];
        $groups = [];

        // <langDir>/<locale>/<group>.php
        foreach (glob($langDir . '/*/*.php') ?: [] as $file) {
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

            $group = basename($file, '.php');
            $groups[strtolower($group)] = true;
            self::collect($return->expr, $group, $keys);
        }

        return new self($keys, $groups);
    }

    /**
     * @param  array<string, true>  $keys
     */
    private static function collect(Node\Expr\Array_ $array, string $prefix, array &$keys): void
    {
        foreach ($array->items as $item) {
            if (! $item instanceof Node\Expr\ArrayItem || ! $item->key instanceof Node\Scalar\String_) {
                continue;
            }

            $key = $prefix . '.' . $item->key->value;
            $keys[strtolower($key)] = true;

            if ($item->value instanceof Node\Expr\Array_) {
                self::collect($item->value, $key, $keys);
            }
        }
    }
}
