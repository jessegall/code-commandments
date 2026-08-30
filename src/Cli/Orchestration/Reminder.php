<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

/**
 * One reminder file, read as the thing a hook actually SAYS — which is less than the file holds.
 */
final readonly class Reminder
{
    /**
     * What $body says, with $holes filled. Its own scaffolding is dropped first, so a hole carrying
     * markdown — a whole role document, a procedure — keeps the headings inside it.
     */
    public static function spoken(string $body, Holes $holes): string
    {
        return trim($holes->fill(self::prose($body)));
    }

    /**
     * $body without the parts written for its EDITOR. A reminder has two audiences and only one is the
     * agent: the heading names it in a listing and the comment says what the holes are and how to switch
     * it off, both addressed to somebody with the file open. Speaking them puts `# journal-quiet` and a
     * paragraph of instructions in front of an agent that asked for one line.
     */
    private static function prose(string $body): string
    {
        $said = [];
        $commenting = false;

        foreach (explode("\n", $body) as $line) {
            $opens = str_contains($line, '<!--');
            $closes = str_contains($line, '-->');

            if ($commenting) {
                $commenting = ! $closes;

                continue;
            }

            if ($opens) {
                $commenting = ! $closes;

                continue;
            }

            // The title, and only where it still is one: a `#` below any prose is a heading the reader is
            // meant to see. Measured against what has been SAID rather than the line count, so a file
            // that opens with a blank line still has its title recognised.
            if (trim(implode('', $said)) === '' && str_starts_with(ltrim($line), '# ')) {
                continue;
            }

            $said[] = $line;
        }

        return implode("\n", $said);
    }
}
