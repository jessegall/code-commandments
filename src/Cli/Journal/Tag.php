<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Journal;

use JesseGall\PhpTypes\Option;

/**
 * What an agent's message is FOR, written as a bracketed prefix on its first line — `[!discovery] the
 * pattern already exists`. A compaction summary keeps what was done and loses what was decided, so the tag
 * is how a message says which of the two it carries, cheaply enough to write every time.
 *
 * The words are SPELLED OUT because the user reads them: a `MessageDisplay` hook sees a message only after
 * the terminal has it, so nothing can strip a prefix on its way out. A tag is therefore part of what the
 * agent says, and `[!discovery]` is a word a human can read where `[d]` is a code they must learn. What the
 * user should NOT see is not written into a message at all — it is recorded through the command instead
 * ({@see isSpoken}).
 *
 * This is the ONE home of the vocabulary: the skill that teaches it, the reminder that resurfaces it, the
 * instructions a compaction is summarised under and the digest's own trimming all project from here.
 */
enum Tag: string
{
    /**
     * The mark for a fact that must reach the far side of every compaction — the one tier the digest
     * never trims and the compaction instructions always carry.
     */
    case Pinned = '!pinned';

    case Correction = '!correction';

    case Blocked = '!blocked';

    case Start = '!start';

    case End = '!end';

    case Discovery = '!discovery';

    case Reply = '!reply';

    case Info = '!info';

    case Done = '!done';

    private const string OPEN = '[';

    private const string CLOSE = ']';

    /**
     * The tag $text opens with, reading the bracketed prefix off the front of a message. Absent when the
     * message carries none, which is a fact about that message rather than a failure.
     *
     * @return Option<self>
     */
    public static function parse(string $text): Option
    {
        $text = ltrim($text);

        if (! str_starts_with($text, self::OPEN)) {
            return Option::none();
        }

        $close = strpos($text, self::CLOSE);

        return $close === false
            ? Option::none()
            : Option::fromNullable(self::tryFrom(substr($text, strlen(self::OPEN), $close - strlen(self::OPEN))));
    }

    /**
     * How the tag is written where a human meets it — `[d]`.
     */
    public function marker(): string
    {
        return self::OPEN . $this->value . self::CLOSE;
    }

    /**
     * What this tag says a message carries, in the words the skill and the reminder both use.
     */
    public function meaning(): string
    {
        return match ($this) {
            self::Pinned => 'MUST survive every compaction — the fact you would be lost without',
            self::Correction => 'a correction — something you had wrong is now right',
            self::Blocked => 'blocked, and on what',
            self::Start => 'starting a piece of work',
            self::End => 'that work is finished',
            self::Discovery => 'a discovery — the real shape of something you did not know',
            self::Reply => 'answering the user',
            self::Info => 'routine narration',
            self::Done => 'a step completed',
        };
    }

    /**
     * Where this tag stands when the digest will not fit — lower survives longer. A correction and a
     * blocker outrank a discovery because they change what the reader should DO next, and the routine
     * tiers go first because their message is already implicit in the work itself.
     */
    public function priority(): int
    {
        return match ($this) {
            self::Pinned => 0,
            self::Correction => 1,
            self::Blocked => 2,
            self::Start, self::End => 3,
            self::Discovery => 4,
            self::Reply => 5,
            self::Info, self::Done => 6,
        };
    }

    /**
     * Does this tag belong in front of a message the user will read? A tag cannot be hidden once written —
     * the terminal has the message before any hook sees it — so the ones that would only be noise are not
     * written into a message at all: they are recorded through `commandments journal` instead, where they
     * reach the index without reaching the user.
     */
    public function isSpoken(): bool
    {
        return match ($this) {
            self::Start, self::End, self::Discovery, self::Correction, self::Blocked => true,
            self::Pinned, self::Reply, self::Info, self::Done => false,
        };
    }

    /**
     * Does this fact travel over every compaction boundary, whatever else is dropped?
     */
    public function isPinned(): bool
    {
        return $this === self::Pinned;
    }

    /**
     * Does this tag begin a piece of work? A {@see Start} with no {@see End} after it is UNFINISHED work
     * — live state rather than history, and the most valuable line a post-compaction digest carries.
     */
    public function isSpanOpener(): bool
    {
        return $this === self::Start;
    }

    public function isSpanCloser(): bool
    {
        return $this === self::End;
    }

    /**
     * The tags an agent WRITES, one per line — what the reminder prints and what the skill publishes, so
     * neither can drift from the cases above. The unspoken ones are left out: they are recorded through the
     * command, so a list of prefixes to type is not where they belong.
     */
    public static function vocabulary(): string
    {
        $lines = [];

        foreach (self::cases() as $tag) {
            if (! $tag->isSpoken()) {
                continue;
            }

            $marker = $tag->marker();
            $lines[] = '  ' . $marker . str_repeat(' ', max(1, 14 - mb_strlen($marker))) . $tag->meaning();
        }

        return implode("\n", $lines);
    }
}
