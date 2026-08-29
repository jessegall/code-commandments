<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Journal;

use JesseGall\CodeCommandments\Cli\Text;

/**
 * Chooses what is worth reading out of a stretch of conversation. A transcript is far too long to hand
 * back whole and a summary of it is what lost the decisions in the first place, so this SELECTS: the
 * user's own words untouched, enough either side of them to know what they were answering, and — through
 * the long stretches where the agent worked alone — only the messages that said they carried something.
 */
final class Digest
{
    /**
     * How many of the agent's messages before a prompt are kept. A bare "yes please" means nothing without
     * the thing it answered, and the answer is usually the message right before it.
     */
    private const int LEADING = 2;

    /**
     * How many after it — what the agent said it would do about what was just said.
     */
    private const int TRAILING = 1;

    /**
     * How many messages may be skipped before the gap is worth mentioning. Below this it is a pause;
     * above it, the agent was working alone and the reader should know.
     */
    private const int GAP = 3;

    /**
     * How far a message is indented from the speaker's label — the width of `USER  ` and `  me  `, so a
     * wrapped line lands under the words rather than under the name.
     */
    private const int GUTTER = 6;

    /**
     * @param  list<Line>  $lines
     */
    public function __construct(private readonly array $lines) {}

    /**
     * The lines of THIS digest that somebody said.
     *
     * @return list<Line>
     */
    private function spoken(): array
    {
        return self::spokenIn($this->lines);
    }

    /**
     * The lines of $lines that somebody SAID, with a prompt the user kept typing counted once. The harness
     * records a message as it is sent and again when more of it follows, so the same words arrive twice
     * with the second carrying the rest; the earlier is a prefix of the later, which identifies it.
     *
     * @param  list<Line>  $lines
     * @return list<Line>
     */
    public static function spokenIn(array $lines): array
    {
        $spoken = array_values(array_filter($lines, fn (Line $line) => $line->isSpeech() && $line->text !== ''));
        $kept = [];

        foreach ($spoken as $at => $line) {
            $next = $spoken[$at + 1] ?? null;

            if ($next !== null && $line->isPrompt() && $next->isPrompt() && str_starts_with($next->text, $line->text)) {
                continue;
            }

            $kept[] = $line;
        }

        return $kept;
    }

    /**
     * The chosen lines, in order.
     *
     * @return list<Line>
     */
    public function selected(): array
    {
        $speech = $this->spoken();
        $keep = [];

        foreach ($speech as $at => $line) {
            if ($this->isWorthKeeping($speech, $at, $line)) {
                $keep[$at] = $line;
            }
        }

        return array_values($keep);
    }

    /**
     * The digest as it reads, with the pinned facts first and the stretches the agent worked alone through
     * marked rather than silently dropped.
     */
    public function render(): string
    {
        $speech = $this->spoken();
        $written = [];
        $previous = -1;

        foreach ($speech as $at => $line) {
            if (! $this->isWorthKeeping($speech, $at, $line)) {
                continue;
            }

            $skipped = $at - $previous - 1;

            if ($previous >= 0 && $skipped >= self::GAP) {
                $written[] = "      ⋯ {$skipped} messages ⋯";
            }

            $written[] = $this->line($line);
            $previous = $at;
        }

        return implode("\n", [...$this->pinned($speech), ...$written]);
    }

    /**
     * Is this line one the reader needs? The user's own words always; the agent's when they said what they
     * carried, or when they sit close enough to a prompt to be its context.
     *
     * @param  list<Line>  $speech
     */
    private function isWorthKeeping(array $speech, int $at, Line $line): bool
    {
        if ($line->isPrompt()) {
            return true;
        }

        if ($line->tag()->isSome()) {
            return true;
        }

        return $this->isNearAPrompt($speech, $at);
    }

    /**
     * Does a prompt stand close enough behind or ahead of $at for this line to be its context?
     *
     * @param  list<Line>  $speech
     */
    private function isNearAPrompt(array $speech, int $at): bool
    {
        for ($near = $at - self::TRAILING; $near <= $at + self::LEADING; $near++) {
            if (($speech[$near] ?? null)?->isPrompt() === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * The facts marked to outlive every compaction, gathered at the top where they cannot be missed.
     *
     * @param  list<Line>  $speech
     * @return list<string>
     */
    private function pinned(array $speech): array
    {
        $pinned = array_filter($speech, fn (Line $line) => $line->tag()->isSomeAnd(fn (Tag $tag) => $tag->isPinned()));

        if ($pinned === []) {
            return [];
        }

        return [Text::heading('pinned'), '', ...array_map($this->line(...), $pinned), ''];
    }

    /**
     * One line as it reads — the user in their own words, the agent indented behind them.
     */
    private function line(Line $line): string
    {
        return $line->isPrompt()
            ? 'USER  ' . $this->wrapped($line->text)
            : '  me  ' . $this->wrapped($line->text);
    }

    /**
     * $text laid out under the speaker's label — wrapped to the terminal, every later line indented to sit
     * beneath the first. A paragraph the window folds where it likes is a wall; nothing is CUT, since the
     * user's words are the whole reason for reading.
     */
    private function wrapped(string $text): string
    {
        return Text::reflow($text, self::GUTTER);
    }
}
