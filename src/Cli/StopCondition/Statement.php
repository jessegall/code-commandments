<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\StopCondition;

/**
 * What the user actually said, and the id it keeps. The id is STABLE — never renumbered, never reused —
 * so a `met 7` cannot strike a different condition than the one the reader counted, and the two travel
 * together because a condition without its id cannot be acted on and an id without its words means
 * nothing to anybody.
 */
final readonly class Statement
{
    public function __construct(
        public int $id,
        public string $text,
    ) {}

    public function is(string $text): bool
    {
        return $this->text === $text;
    }
}
