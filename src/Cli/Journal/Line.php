<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Journal;

use JesseGall\PhpTypes\Option;

/**
 * One line of a session transcript, read. It knows what it IS ({@see Category}), when it was written and
 * what was said — the full text, because the transcript is the record and this is the thing that reads it.
 */
final readonly class Line
{
    public function __construct(
        public Category $category,
        public string $at,
        public string $text,
    ) {}

    /**
     * The tag this line's text opens with, for a line the agent wrote.
     *
     * @return Option<Tag>
     */
    public function tag(): Option
    {
        return Tag::parse($this->text);
    }

    public function isSpeech(): bool
    {
        return $this->category->isSpeech();
    }

    /**
     * Is this the user speaking? Their words are the tier a digest never trims.
     */
    public function isPrompt(): bool
    {
        return $this->category === Category::Prompt;
    }

    /**
     * Does this line mention $term, ignoring case? A journal search reads what was SAID, so this asks of
     * the text and nothing else.
     */
    public function mentions(string $term): bool
    {
        return mb_stripos($this->text, $term) !== false;
    }
}
