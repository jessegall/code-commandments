<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * Lifts one sin's worked example out of a rendered `SKILL.md`. Reading the published document rather
 * than re-rendering it is the point: what `info` shows and what the skill teaches are then the same
 * text by construction, second language and all.
 */
final class SkillSection
{
    private const string HEADING = '### ';

    private const string SECTION = '## ';

    /**
     * The `### …` block(s) of $document whose heading names $sin — a rule taught in two languages has
     * one per language, and they are returned together. Empty when the document has none, which is
     * every skill whose fixture has no marked scenario yet.
     */
    public static function forSin(string $document, string $sin): string
    {
        $blocks = [];
        $current = null;

        foreach (explode("\n", $document) as $line) {
            if (str_starts_with($line, self::HEADING)) {
                // Close first: the NEXT example's heading is what ends this one, so reassigning
                // without closing threw away the block that had just been collected.
                self::close($current, $blocks);
                $current = self::names($line, $sin) ? [$line] : null;

                continue;
            }

            if (str_starts_with($line, self::SECTION)) {
                $current = self::close($current, $blocks);

                continue;
            }

            if ($current !== null) {
                $current[] = $line;
            }
        }

        self::close($current, $blocks);

        return trim(implode("\n\n", array_map(self::code(...), $blocks)));
    }

    /**
     * A block reduced to the fenced CODE, keeping the heading only for the language it names — the
     * prose above the fence is the sin's own description, which the caller has already shown.
     *
     * @param  list<string>  $block
     */
    private static function code(array $block): string
    {
        $heading = array_shift($block) ?? '';
        $language = explode('— in ', $heading);
        $fence = array_search(true, array_map(static fn (string $line): bool => str_starts_with($line, '```'), $block), true);

        if ($fence === false) {
            return '';
        }

        $code = trim(implode("\n", array_slice($block, (int) $fence)));

        return count($language) > 1 ? 'In ' . trim($language[1]) . ":\n\n{$code}" : $code;
    }

    /**
     * Does this heading name $sin? A heading lists every sin the example demonstrates, separated by
     * `·`, and may add the language it is written in — so the name is matched against the parts
     * rather than the whole line.
     */
    private static function names(string $heading, string $sin): bool
    {
        $title = trim(substr($heading, strlen(self::HEADING)));

        foreach (explode('·', explode('— in ', $title)[0]) as $part) {
            if (trim($part) === $sin) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>|null  $current
     * @param  list<list<string>>  $blocks
     */
    private static function close(?array $current, array &$blocks): null
    {
        if ($current !== null) {
            $blocks[] = $current;
        }

        return null;
    }
}
