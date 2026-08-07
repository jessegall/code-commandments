<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Doc;

/**
 * The `<!-- BEGIN: name … -->` / `<!-- END: name -->` block a document reserves for generated
 * content — the one mechanism behind every auto-generated table (README, skills). A document DECLARES
 * where a table goes; the generator fills it, so the prose around it stays hand-written and the table
 * inside it is never hand-maintained.
 */
final class GeneratedBlock
{
    /**
     * Replace what stands between the markers. Null when the document has no such block; a
     * {@see MalformedBlock} when it has something that cannot be read as one.
     *
     * A marker counts only when it is ALONE on its line. These blocks now live in files a human
     * writes, and our own documentation SHOWS the markers — so a mention of one in prose is
     * ordinary, and matching it as the real thing would splice from the mention to the real END,
     * taking the words in between. For the same reason two BEGINs are refused rather than resolved
     * to the first: choosing a pair is guessing which one the human meant.
     */
    public static function replace(string $document, string $name, string $content): ?string
    {
        $lines = explode("\n", $document);
        $begins = self::linesEqualling($lines, "<!-- BEGIN: {$name} ", true);
        $ends = self::linesEqualling($lines, self::end($name), false);

        if ($begins === [] && $ends === []) {
            return null;
        }

        if (count($begins) > 1 || count($ends) > 1) {
            throw MalformedBlock::of($name, 'the document carries more than one of them');
        }

        if ($begins === [] || $ends === []) {
            throw MalformedBlock::of($name, $begins === [] ? 'it has an END marker with no BEGIN' : 'it has a BEGIN marker with no END');
        }

        if ($ends[0] < $begins[0]) {
            throw MalformedBlock::of($name, 'its END marker stands above its BEGIN');
        }

        // $content verbatim between the two marker LINES — it arrives with the blank lines its
        // author wants around it, and a block that reflows on every refresh is a block that shows
        // up in every diff.
        return implode("\n", array_slice($lines, 0, $begins[0] + 1))
            . $content
            . implode("\n", array_slice($lines, $ends[0]));
    }

    /**
     * The BEGIN marker line for a block — so a document that wants one can be written (or checked)
     * against the exact form {@see replace} looks for.
     */
    public static function begin(string $name, string $command): string
    {
        return "<!-- BEGIN: {$name} (auto-generated, run `{$command}`) -->";
    }

    public static function end(string $name): string
    {
        return "<!-- END: {$name} -->";
    }

    /**
     * The indexes of the lines that ARE $marker, ignoring surrounding whitespace — a prefix match
     * for BEGIN, whose line carries the regeneration command, exact for END.
     *
     * @param  list<string>  $lines
     * @return list<int>
     */
    private static function linesEqualling(array $lines, string $marker, bool $prefix): array
    {
        $found = [];

        foreach ($lines as $index => $line) {
            $line = trim($line);
            $matches = $prefix
                ? str_starts_with($line, $marker) && str_ends_with($line, '-->')
                : $line === $marker;

            if ($matches) {
                $found[] = $index;
            }
        }

        return $found;
    }
}
