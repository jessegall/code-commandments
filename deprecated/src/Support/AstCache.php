<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use PhpParser\Error as ParseError;
use PhpParser\Node;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * A per-process parse memo keyed by content hash, so a file is parsed ONCE per run and the same AST node array is shared across every prophet (bounded by a small LRU).
 */
final class AstCache
{
    /** How many parsed files to keep — small: only the in-flight file(s) are hot. */
    private const MAX_ENTRIES = 8;

    /** @var array<string, list<Node>|null> insertion-ordered LRU, content-hash => AST */
    private static array $cache = [];

    private static ?Parser $parser = null;

    private static int $hits = 0;

    private static int $misses = 0;

    /**
     * Parse $content (memoized). Returns the AST node list, or null on a parse
     * error (cached too, so a broken file is not re-parsed by every prophet).
     *
     * @return list<Node>|null
     */
    public static function parse(string $content): ?array
    {
        $key = \sha1($content);

        if (\array_key_exists($key, self::$cache)) {
            self::$hits++;

            // Touch: move to the most-recently-used end.
            $ast = self::$cache[$key];
            unset(self::$cache[$key]);
            self::$cache[$key] = $ast;

            return $ast;
        }

        self::$misses++;
        self::$parser ??= (new ParserFactory)->createForNewestSupportedVersion();

        try {
            $ast = self::$parser->parse($content);
        } catch (ParseError) {
            $ast = null;
        }

        self::$cache[$key] = $ast;

        if (\count(self::$cache) > self::MAX_ENTRIES) {
            \array_shift(self::$cache); // evict the least-recently-used
        }

        return $ast;
    }

    /** Drop every cached AST (e.g. between independent runs in one process). */
    public static function flush(): void
    {
        self::$cache = [];
    }

    /**
     * Hit/miss counters — misses ≈ distinct files parsed, hits ≈ the redundant
     * parses this memo eliminated. Used to verify the in-run sharing win.
     *
     * @return array{hits: int, misses: int}
     */
    public static function stats(): array
    {
        return ['hits' => self::$hits, 'misses' => self::$misses];
    }

    public static function resetStats(): void
    {
        self::$hits = 0;
        self::$misses = 0;
    }
}
