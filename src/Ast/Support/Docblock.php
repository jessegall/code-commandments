<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Support\ClassName;

/**
 * The SHAPE of a docblock — its delimiters, not its prose. A docblock reads as a block: the opening
 * delimiter stands on its own line, every line of content carries one star, and the closing delimiter
 * stands on its own. The single home of that form, so the rule that finds a one-liner and the fix that
 * expands it can never disagree about what "canonical" means.
 */
final class Docblock
{
    /**
     * Does this docblock share a line with its own text — the whole thing on one line, or a first/last
     * line carrying content next to a delimiter? Empty of content, it is left alone: there is nothing
     * to put on a line of its own.
     */
    public static function isInline(string $text): bool
    {
        $lines = self::lines($text);

        if (self::contentOf($text) === []) {
            return false;
        }

        return trim($lines[0]) !== '/**' || trim($lines[count($lines) - 1]) !== '*/';
    }

    /**
     * The docblock rewritten in canonical form at $indent — the original content, verbatim and in order,
     * one `*` line each, between delimiters that stand alone.
     */
    public static function canonical(string $text, string $indent): string
    {
        $body = '';

        foreach (self::contentOf($text) as $line) {
            $body .= $line === '' ? "\n{$indent} *" : "\n{$indent} * {$line}";
        }

        return "/**{$body}\n{$indent} */";
    }

    /**
     * Several docblocks merged into ONE canonical block at $indent — each block's content in order,
     * separated by a blank line so the paragraphs stay distinct. Text is never rewritten, only rehoused.
     *
     * @param  list<string>  $texts
     */
    public static function merge(array $texts, string $indent): string
    {
        $lines = [];

        foreach ($texts as $text) {
            $content = self::contentOf($text);

            if ($content === []) {
                continue;
            }

            if ($lines !== []) {
                $lines[] = '';
            }

            $lines = [...$lines, ...$content];
        }

        $body = '';

        foreach ($lines as $line) {
            $body .= $line === '' ? "\n{$indent} *" : "\n{$indent} * {$line}";
        }

        return "/**{$body}\n{$indent} */";
    }

    /**
     * Can these blocks be folded into ONE coherent block? Two blocks that each declare the SAME tag
     * (two `@return`, two `@var`, the same `@param $x`) cannot: the fold would hand a reader a block
     * whose own tags contradict each other, and since the shadowed block was the one nobody ever saw,
     * PROMOTING its tag next to the live one makes the documentation worse than the sin (#417). Prose
     * always folds — only a repeated tag refuses.
     *
     * @param  list<string>  $texts
     */
    public static function foldable(array $texts): bool
    {
        $seen = [];

        foreach ($texts as $text) {
            foreach (array_unique(self::tags($text)) as $tag) {
                if (in_array($tag, $seen, true)) {
                    return false;
                }

                $seen[] = $tag;
            }
        }

        return true;
    }

    /**
     * The block with ONE tag's type re-headed — `@param DataCollection<int, X> $content` becomes
     * `@param array<int, X> $content` for `retype($text, 'content', 'Spatie\…\DataCollection',
     * 'array')`. The tag documenting `$name` is found by the name it speaks about, so sibling
     * `@param`s are untouched.
     *
     * Only the OUTERMOST head changes, and only when it really is $from: the generic arguments, the
     * description, the spacing and every other line survive byte-for-byte. A docblock an author
     * wrote is not a scribe's to reformat — the fix owes it one true word, not a new block. A
     * property's `@var` is re-headed the same way, since a promoted property is documented either way.
     */
    public static function retype(string $text, string $name, string $from, string $to): string
    {
        $name = preg_quote($name, '/');
        $rehead = static fn (array $m): string => $m[1] . (self::reheaded(rtrim($m[2]), $from, $to) ?? $m[2]) . $m[3];

        // The type runs up to the `$name` it documents, NOT to the first space: `DataCollection<int,
        // NodeData>` carries spaces inside its own generic arguments.
        $text = preg_replace_callback('/(@param\s+)(.+?)(\s+(?:\.\.\.)?&?\$' . $name . '\b)/', $rehead, $text) ?? $text;
        $text = preg_replace_callback('/(@var\s+)(.+?)(\s+\$' . $name . '\b)/', $rehead, $text) ?? $text;

        // A property's `@var` names no variable — its type is the rest of the line.
        return preg_replace_callback('/(@var\s+)([^\r\n]+?)(\s*)$/m', $rehead, $text) ?? $text;
    }

    /**
     * Does this block name $fqcn (or its short name) anywhere in a tag's type? The question an import
     * has to answer before it can be dropped: a `use` line is dead only once nothing SPELLS the name,
     * and a docblock spells it as surely as a signature does — `@param array<string,
     * DataCollection<string, X>>` keeps the import alive even after every native type has moved on.
     */
    public static function mentionsType(string $text, string $fqcn): bool
    {
        $short = ClassName::short($fqcn);

        return preg_match('/(?<![\w\\\\])' . preg_quote($short, '/') . '\b/', $text) === 1;
    }

    /**
     * $type with its outermost head swapped from $from to $to, or null when the head is something
     * else. `?DataCollection<int, X>` and the fully-qualified spelling both count — a nullable marker
     * is not part of the name, and an author may write either.
     */
    private static function reheaded(string $type, string $from, string $to): ?string
    {
        $prefix = str_starts_with($type, '?') ? '?' : '';
        $head = substr($type, strlen($prefix));
        $short = ClassName::short($from);

        foreach ([$from, '\\' . $from, $short] as $spelling) {
            if ($head === $spelling) {
                return $prefix . $to;
            }

            if (str_starts_with($head, $spelling . '<')) {
                return $prefix . $to . substr($head, strlen($spelling));
            }
        }

        return null;
    }

    /**
     * The tags this block declares, each as the key that identifies WHAT it documents — the tag name,
     * plus the variable for a `@param`/`@property`, since those legitimately repeat per name while a
     * `@return` or `@var` speaks about the declaration once.
     *
     * @return list<string>
     */
    private static function tags(string $text): array
    {
        $tags = [];

        foreach (self::contentOf($text) as $line) {
            $words = self::words($line);

            if ($words === [] || ! str_starts_with($words[0], '@')) {
                continue;
            }

            $tags[] = $words[0] . self::subject(array_slice($words, 1));
        }

        return $tags;
    }

    /**
     * The `$name` a tag speaks about, as a suffix for its key — empty when it names none, so the tag
     * itself is the whole key.
     *
     * @param  list<string>  $words
     */
    private static function subject(array $words): string
    {
        foreach ($words as $word) {
            if (str_starts_with($word, '$')) {
                return ' ' . $word;
            }
        }

        return '';
    }

    /**
     * @return list<string>  the line's whitespace-separated words
     */
    private static function words(string $line): array
    {
        $words = [];

        foreach (explode(' ', str_replace("\t", ' ', $line)) as $word) {
            if ($word !== '') {
                $words[] = $word;
            }
        }

        return $words;
    }

    /**
     * The docblock's lines — delimiters and all — with every `@<tag>` entry removed. What a
     * regenerator keeps when it is about to write a fresh set of that tag: the hand-written prose
     * survives, the generated lines go. A tag is matched whole, so `@method` never strips a
     * `@methods`.
     *
     * @return list<string>
     */
    public static function withoutTag(string $text, string $tag): array
    {
        $kept = [];

        foreach (self::lines($text) as $line) {
            if (preg_match('/^\s*\*?\s*@' . preg_quote($tag, '/') . '\b/', $line) !== 1) {
                $kept[] = $line;
            }
        }

        return $kept;
    }

    /**
     * The docblock's content lines, stripped of the delimiters and the per-line `*`, with any leading
     * and trailing blank lines dropped. A blank line INSIDE (the paragraph break before `@param`) is
     * kept as an empty entry.
     *
     * @return list<string>
     */
    private static function contentOf(string $text): array
    {
        $body = trim($text);

        // The delimiters come off FIRST — strip the per-line star before them and the opening `/**`
        // reads as content that happens to begin with slashes.
        if (str_starts_with($body, '/**')) {
            $body = substr($body, 3);
        }

        if (str_ends_with($body, '*/')) {
            $body = substr($body, 0, -2);
        }

        $lines = [];

        foreach (self::lines($body) as $line) {
            $line = trim($line);
            $lines[] = rtrim(str_starts_with($line, '*') ? ltrim(substr($line, 1)) : $line);
        }

        while ($lines !== [] && $lines[0] === '') {
            array_shift($lines);
        }

        while ($lines !== [] && $lines[count($lines) - 1] === '') {
            array_pop($lines);
        }

        return array_values($lines);
    }

    /**
     * @return list<string>
     */
    private static function lines(string $text): array
    {
        return preg_split('/\R/', $text) ?: [$text];
    }
}
