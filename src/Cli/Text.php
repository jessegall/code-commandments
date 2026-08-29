<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * Laying words out for a person to read. A terminal will happily print a four-hundred-character paragraph
 * as one line and let the window fold it wherever it likes, which turns a list of considered facts into a
 * wall — so anything a human reads is wrapped here, at the width their terminal actually has.
 */
final class Text
{
    /**
     * The width used when the terminal will not say — the width a terminal has had since it was furniture.
     */
    private const int ASSUMED = 80;

    /**
     * Wider than this and the eye loses the start of the next line, so a very wide window is not filled.
     */
    private const int COMFORTABLE = 96;

    private static ?int $width = null;

    /**
     * $text wrapped to the terminal, with every line after the first indented by $indent so a wrapped
     * paragraph stays visibly one thing. Existing line breaks are kept — they were meant.
     */
    public static function wrap(string $text, int $indent = 0): string
    {
        $lines = [];

        foreach (explode("\n", $text) as $line) {
            $wrapped = wordwrap(rtrim($line), max(20, self::width() - $indent), "\n", cut_long_words: false);
            $lines = [...$lines, ...explode("\n", $wrapped)];
        }

        // EVERY line after the first is indented, whether the wrap made it or the text already had it —
        // the caller has put a label in front of the first, and the rest must sit under the words.
        return implode("\n" . str_repeat(' ', $indent), $lines);
    }

    /**
     * $text with its paragraphs joined back into single lines before wrapping. A message arrives already
     * hard-wrapped to whatever width it was written at, and wrapping that AGAIN at a narrower one leaves
     * two words stranded on every other line — so prose is reflowed, and anything with a shape of its own
     * (a list, a table, an indented block, a fence) is left exactly as it was written.
     */
    public static function reflow(string $text, int $indent = 0): string
    {
        $blocks = [];

        foreach (explode("\n\n", $text) as $block) {
            $lines = explode("\n", trim($block, "\n"));
            $blocks[] = self::isProse($lines) ? implode(' ', array_map(trim(...), $lines)) : $block;
        }

        return self::wrap(implode("\n\n", $blocks), $indent);
    }

    /**
     * Are these lines plain prose — nothing that would lose its meaning if the breaks between them moved?
     * An indent, a bullet, a table rule, a heading or a fence all say the breaks were deliberate.
     *
     * @param  list<string>  $lines
     */
    private static function isProse(array $lines): bool
    {
        foreach ($lines as $line) {
            foreach ([' ', "\t", '-', '*', '|', '#', '>', '`', '+'] as $marker) {
                if (str_starts_with($line, $marker)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * A heading with a rule after it, so a reader can see where one answer ends and the next begins.
     */
    public static function heading(string $title): string
    {
        $rule = max(4, self::width() - mb_strlen($title) - 4);

        return "\n── {$title} " . str_repeat('─', $rule);
    }

    /**
     * How wide the terminal is. Asked once: it costs a subprocess, and a window that resizes mid-read is
     * not worth a process per line.
     */
    public static function width(): int
    {
        if (self::$width !== null) {
            return self::$width;
        }

        $columns = (int) (getenv('COLUMNS') ?: trim((string) @shell_exec('tput cols 2>/dev/null')));

        return self::$width = min(self::COMFORTABLE, $columns > 20 ? $columns : self::ASSUMED);
    }
}
