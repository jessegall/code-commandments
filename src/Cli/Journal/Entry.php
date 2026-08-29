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
     * How many fields a line has. A line with fewer is not an entry, so it is skipped rather than guessed at.
     */
    private const int FIELDS = 6;

    /**
     * @param  Option<Tag>  $tag  absent when the message carried no prefix
     */
    public function __construct(
        public Kind $kind,
        public string $at,
        public string $turnId,
        public string $messageId,
        public Option $tag,
        public string $text,
    ) {}

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
        $fields = explode(self::SEPARATOR, $line, self::FIELDS); // The text is last, so it keeps any tab of its own.

        if (count($fields) !== self::FIELDS) {
            return Option::none();
        }

        [$kind, $at, $turnId, $messageId, $tag, $text] = $fields;

        return Option::fromNullable(Kind::tryFrom($kind))
            ->map(fn (Kind $kind) => new self($kind, $at, $turnId, $messageId, Option::fromNullable(Tag::tryFrom($tag)), $text));
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
