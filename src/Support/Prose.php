<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * English prose reduced to the words that CARRY meaning — the one home for turning a comment into
 * comparable tokens, so an analysis can ask "does this sentence say anything the code doesn't?". Two
 * moves: drop the pure grammar ({@see FILLER}), and {@see stem} what's left so `saves`, `saved`,
 * `saving` and `save` all compare equal. Negations and modals ("never", "always", "must", "because")
 * count as content — they are where a short comment earns its line.
 */
final class Prose
{
    /**
     * Pure grammar — the words a sentence needs to hold together, carrying no information itself.
     *
     * @var list<string>
     */
    private const array FILLER = [
        'the', 'a', 'an', 'and', 'or', 'of', 'to', 'in', 'on', 'at', 'by', 'for', 'from', 'with',
        'into', 'onto', 'this', 'that', 'these', 'those', 'it', 'its', 'we', 'us', 'our', 'you',
        'your', 'they', 'them', 'their', 'he', 'she', 'his', 'her', 'is', 'are', 'was', 'were',
        'be', 'been', 'being', 'am', 'do', 'does', 'did', 'has', 'have', 'had', 'will', 'as',
        'here', 'there', 'then', 'so', 'up', 'out', 'per', 'via', 'each',
        // Prepositions a sentence needs and code never spells — "loop OVER the entries", "read it
        // BACK", "pull them THROUGH". Dropping them is what lets a narration line up with its statement.
        'over', 'off', 'about', 'upon', 'across', 'through', 'between', 'during', 'within', 'against',
        'back', 'than',
    ];

    /**
     * The content words of $text, lower-cased and {@see stem}med, in order — grammar dropped, and
     * anything that isn't a letter treated as a separator (so `$order->totalPrice()` and
     * "order total price" reduce alike).
     *
     * @return list<string>
     */
    public static function words(string $text): array
    {
        $words = [];

        foreach (preg_split('/[^A-Za-z]+/', self::spaceCamelCase($text)) ?: [] as $token) {
            $word = strtolower($token);

            if (strlen($word) < 2) {
                continue; // A single letter carries nothing — `$i`, a stray `s` from an apostrophe.
            }

            if (in_array($word, self::FILLER, true)) {
                continue;
            }

            $words[] = self::stem($word);
        }

        return $words;
    }

    /**
     * A crude, symmetric stem: strip one plural/participle ending, then a trailing `e`, so both sides
     * of a comparison land on the same token (`settle`/`settled` → `settl`, `price`/`prices` → `pric`).
     * Stops at three characters, so `used` keeps its shape.
     */
    public static function stem(string $word): string
    {
        foreach (['ing', 'ies', 'ed', 'es', 's'] as $ending) {
            if (! str_ends_with($word, $ending)) {
                continue;
            }

            if (strlen($word) - strlen($ending) < 3) {
                continue;
            }

            $word = $ending === 'ies'
                ? substr($word, 0, -3) . 'y'
                : substr($word, 0, -strlen($ending));

            break;
        }

        return str_ends_with($word, 'e') && strlen($word) >= 4 ? substr($word, 0, -1) : $word;
    }

    /**
     * Break camelCase and PascalCase runs apart so an identifier tokenises into its words —
     * `totalPrice` → `total Price`. Snake/kebab separators need no help; they aren't letters.
     */
    private static function spaceCamelCase(string $text): string
    {
        return (string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', $text);
    }
}
