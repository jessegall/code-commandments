<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\State;

/**
 * The value lines of a marker in the OLD positional format, read BY POSITION and total at every
 * one: a file written by an older release may be shorter than the one that reads it, and a line
 * that was never written is simply the empty value for its kind.
 *
 * That answer lives here rather than at each read. A `?? ''` spelled out per line is the same
 * decision made eight times, and the eighth is the one that gets it wrong.
 */
final readonly class LegacyLines
{
    /**
     * @param  list<string>  $lines
     */
    public function __construct(private array $lines = []) {}

    public function text(int $index): string
    {
        return $this->lines[$index] ?? '';
    }

    public function int(int $index): int
    {
        return (int) $this->text($index);
    }

    /**
     * Was the line at $index actually written — as opposed to reading as empty because it is not
     * there? The two are the same value and a different fact.
     */
    public function has(int $index): bool
    {
        return isset($this->lines[$index]);
    }

    /**
     * Is the line at $index a whole number — the test that tells a header line from a condition
     * that happened to sit in the same place.
     */
    public function isNumeric(int $index): bool
    {
        return ctype_digit($this->text($index));
    }

    /**
     * Every line from $index onward.
     *
     * @return list<string>
     */
    public function from(int $index): array
    {
        return array_slice($this->lines, $index);
    }

    /**
     * Is the line at $index the old format's `1` flag?
     */
    public function isFlagged(int $index): bool
    {
        return $this->text($index) === '1';
    }
}
