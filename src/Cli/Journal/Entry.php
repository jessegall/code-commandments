<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Journal;

use JesseGall\PhpTypes\Option;

/**
 * One filed line of the journal — who said it, when, under which {@see Tag}, and the first line of what
 * was said. The full text stays in the transcript, which is the record; this is the INDEX over it, so an
 * entry carries only enough to find and rank the thing it points at.
 */
final readonly class Entry
{
    /**
     * How a line's fields are divided. A tab appears in none of them, so splitting is unambiguous and
     * the text needs no escaping.
     */
    private const string SEPARATOR = "\t";

    /**
     * How many fields every line has carried since the format began. A line with fewer is not an entry, so
     * it is skipped rather than guessed at. The format GROWS in place — a line written before a field
     * existed simply does not carry it, and still reads back — because a journal already on disk holds the
     * open work no summary can reconstruct, and an upgrade that dropped it would be the loss it exists to
     * prevent.
     */
    private const int FIELDS = 6;

    /**
     * What marks the field that names a superseded pin, so the slot cannot be confused with the text that
     * follows it: a line older than the field has no slot at all, and its text would otherwise be read as
     * one the moment it happened to carry a tab.
     */
    private const string SUPERSEDES = '>';

    /**
     * @param  Option<Tag>  $tag  absent when the message carried no prefix
     * @param  ?int  $supersedes  the {@see Pin} this one replaces, for a pinned fact that corrects an
     *                            earlier one. Every other entry supersedes nothing and says so by saying
     *                            nothing, which is why this is the one field with a default: an `Option`
     *                            cannot be one (its constructor is private), and making every caller
     *                            declare "I strike no pin" is ceremony that only breaks them. Read it
     *                            through {@see supersedes}, which is the whole outward spelling.
     */
    public function __construct(
        public Kind $kind,
        public string $at,
        public string $turnId,
        public string $messageId,
        public Option $tag,
        public string $text,
        private ?int $supersedes = null,
    ) {}

    /**
     * A fact pinned to outlive every compaction, optionally striking the pin it corrects. Pins are the one
     * entry nothing says out loud — they are recorded through the command — so this is where they are made.
     *
     * @param  Option<int>  $supersedes
     */
    public static function pin(string $at, string $text, Option $supersedes): self
    {
        return new self(Kind::Mark, $at, '', '', Option::some(Tag::Pinned), $text, $supersedes->unwrapOr(null));
    }

    /**
     * The pin this entry replaces — absent for all but a pinned fact filed to correct an earlier one.
     *
     * @return Option<int>
     */
    public function supersedes(): Option
    {
        return Option::fromNullable($this->supersedes);
    }

    /**
     * A line as the {@see \JesseGall\CodeCommandments\Cli\State\StateFile} keeps it. Newlines are folded
     * out of the text because a filed line IS one line — the rest of the message is in the transcript.
     */
    public function toLine(): string
    {
        return implode(self::SEPARATOR, [
            $this->kind->value,
            $this->at,
            $this->turnId,
            $this->messageId,
            $this->tag->mapOr('', fn (Tag $tag) => $tag->value),
            ...$this->supersedes()->mapOr([], fn (int $pin) => [self::SUPERSEDES . $pin]),
            self::oneLine($this->text),
        ]);
    }

    /**
     * The entry $line records. Absent for a line this format did not write — a hand-edited file stays
     * readable rather than fatal.
     *
     * @return Option<self>
     */
    public static function fromLine(string $line): Option
    {
        $fields = explode(self::SEPARATOR, $line);

        if (count($fields) < self::FIELDS) {
            return Option::none();
        }

        [$kind, $at, $turnId, $messageId, $tag] = $fields;
        $supersedes = self::supersededPin($fields[self::FIELDS - 1]);
        // Everything past the marked slot is the text, rejoined — so a line keeps any tab of its own.
        $text = implode(self::SEPARATOR, array_slice($fields, self::FIELDS - ($supersedes->isNone() ? 1 : 0)));

        return Option::fromNullable(Kind::tryFrom($kind))
            ->map(fn (Kind $kind) => new self($kind, $at, $turnId, $messageId, Option::fromNullable(Tag::tryFrom($tag)), $text, $supersedes->unwrapOr(null)));
    }

    /**
     * The pin $field names, absent when it is not the marked slot at all — which is every line written
     * before the field existed, and every entry that superseded nothing.
     *
     * @return Option<int>
     */
    private static function supersededPin(string $field): Option
    {
        if (! str_starts_with($field, self::SUPERSEDES)) {
            return Option::none();
        }

        return Option::fromNullable(filter_var(
            substr($field, strlen(self::SUPERSEDES)),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1], 'flags' => FILTER_NULL_ON_FAILURE],
        ));
    }

    /**
     * The hour and minute this was filed, UTC. What a reader wants off the stamp when the question is
     * whether a span is still in flight or was abandoned hours ago; the date is the session's own.
     */
    public function time(): string
    {
        return substr($this->at, 11, 5);
    }

    /**
     * Is this entry the user speaking? Their words are the tier the digest never trims.
     */
    public function isUser(): bool
    {
        return $this->kind === Kind::User;
    }

    /**
     * Does this entry travel over every compaction boundary?
     */
    public function isPinned(): bool
    {
        return $this->tag->isSomeAnd(fn (Tag $tag) => $tag->isPinned());
    }

    /**
     * Where this entry stands when the digest will not fit. An untagged agent message ranks below every
     * tagged one — it said nothing about what it carried, so it is the first thing to go.
     */
    public function priority(): int
    {
        return $this->isUser() ? 0 : $this->tag->mapOr(PHP_INT_MAX, fn (Tag $tag) => $tag->priority());
    }

    /**
     * How this entry reads in a digest — the tag it wore, then what it said.
     */
    public function render(): string
    {
        return $this->tag->mapOr($this->text, fn (Tag $tag) => $tag->marker() . ' ' . $this->text);
    }

    /**
     * $text as a single line: the first one, with the blank ones before it skipped, so a message opening
     * on a newline still files the words it actually began with.
     */
    private static function oneLine(string $text): string
    {
        foreach (explode("\n", $text) as $line) {
            if (trim($line) !== '') {
                return trim($line);
            }
        }

        return '';
    }
}
