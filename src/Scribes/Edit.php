<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes;

use JesseGall\CodeCommandments\Span;

/**
 * One byte-range replacement in a source string: the half-open range `[start, end)` becomes `text`.
 * Half-open is {@see Span}'s convention and therefore the engine's — a pure insertion is
 * `start === end`, consuming nothing. (php-parser reports an INCLUSIVE end; the one `+ 1` that
 * converts it lives in {@see Scribe::replaceNode}, so nothing else has to remember which
 * convention it is holding.)
 */
final readonly class Edit
{
    public function __construct(
        public int $start,
        public int $end,
        public string $text,
    ) {}

    /**
     * A pure insertion at $at — nothing is consumed.
     */
    public static function insertAt(int $at, string $text): self
    {
        return new self($at, $at, $text);
    }

    /**
     * Order for application: right-to-left, so an earlier edit's offsets stay valid; and at a
     * SHARED start the wider edit first, so a zero-width insert (stamping an attribute) composes
     * with an abutting replace (dropping a modifier) that begins at the same offset instead of one
     * being skipped as an overlap.
     */
    public static function lastFirst(self $a, self $b): int
    {
        return ($b->start <=> $a->start) ?: ($b->end <=> $a->end);
    }

    public function equals(self $other): bool
    {
        return $this->start === $other->start
            && $this->end === $other->end
            && $this->text === $other->text;
    }

    /**
     * $source with this edit's range replaced by its text.
     */
    public function appliedTo(string $source): string
    {
        return substr($source, 0, $this->start) . $this->text . substr($source, $this->end);
    }
}
