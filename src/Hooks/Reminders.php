<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks;

use JesseGall\PhpTypes\Option;

/**
 * Where a hook's words come from — one file per reminder under `templates/reminders/`, so the wording a
 * nudge arrives in is edited as prose rather than buried in the handler that says it. The file is the
 * ONLY copy: no class keeps a string of its own "as a fallback", since a second copy is one that drifts
 * and the one that drifts is the one nobody reads.
 */
final readonly class Reminders
{
    private const string FOLDER = '/templates/reminders/';

    public function __construct(private string $root) {}

    public static function shipped(): self
    {
        return new self(dirname(__DIR__, 2));
    }

    /**
     * What $name says with $holes filled, absent when this package ships no reminder by that name —
     * which is a typo rather than a choice.
     *
     * @return Option<string>
     */
    public function say(string $name, Holes $holes): Option
    {
        $file = $this->root . self::FOLDER . basename($name) . '.md';

        if (! is_file($file)) {
            return Option::none();
        }

        return Option::some(trim($holes->fill($this->prose((string) file_get_contents($file)))));
    }

    /**
     * $body without the parts written for its EDITOR. A reminder has two audiences and only one is the
     * agent: the heading names it in a listing and the comment says what the holes are and how to switch
     * it off, both addressed to somebody with the file open. Speaking them puts `# journal-quiet` and a
     * paragraph of instructions in front of an agent that asked for one line.
     */
    private function prose(string $body): string
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
