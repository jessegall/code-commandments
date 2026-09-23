<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Scope;

/**
 * The lines of one file that differ from what is committed — what an edit can be held to, so a check
 * speaks about the code just written and not about the rest of the file. A file git does not track is
 * new in every line.
 */
final readonly class ChangedLines
{
    /**
     * @param  list<array{int, int}>  $ranges  each changed run as `[first, last]`, inclusive
     */
    private function __construct(
        private bool $whole,
        private array $ranges,
    ) {}

    public static function everywhere(): self
    {
        return new self(true, []);
    }

    /**
     * The lines a `git diff -U0` of one file adds or rewrites, read from its hunk headers — each run with
     * the line after it, because a comment or docblock written above code belongs to that code, and a
     * finding about it is reported where the code starts. A hunk that only deletes names the line the
     * removal closed up against.
     */
    public static function fromDiff(string $diff): self
    {
        $ranges = [];

        foreach (explode("\n", $diff) as $line) {
            if (! str_starts_with($line, '@@ ')) {
                continue;
            }

            // `@@ -12,3 +14,5 @@` — the third field is where the new side starts and how many lines it runs.
            $added = explode(',', ltrim(explode(' ', $line)[2], '+'));
            $start = (int) $added[0];
            $count = count($added) === 2 ? (int) $added[1] : 1;
            $ranges[] = [$start, $start + $count];
        }

        return new self(false, $ranges);
    }

    public function covers(int $line): bool
    {
        return $this->whole || array_any($this->ranges, static fn (array $range): bool => $line >= $range[0] && $line <= $range[1]);
    }
}
