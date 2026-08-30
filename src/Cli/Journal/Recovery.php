<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Journal;

/**
 * What an agent on the far side of a compaction is HANDED rather than told to fetch: the recovery shipped
 * as three commands to run, and a real compaction measured an orchestrator running none of them, so the
 * reads happen here and their OUTPUT is what it wakes to. It stays small and carries only what a summary
 * provably cannot — which work is still OPEN, and which of the agent's own tags were never filed, the
 * summariser having kept the user's words and rewritten the pins into its own prose — with everything
 * else a pointer; and every line says where it came from, because that same compaction froze an
 * INFERENCE as settled fact and a second worker was dispatched into a lane the first was still writing.
 */
final readonly class Recovery
{
    /**
     * The stretch the summary replaced. The harness writes the `compact_boundary` record and the summary
     * into the transcript BEFORE it re-fires `SessionStart`, so by the time this runs the chunk that just
     * ended is one back — chunk 0 is the empty stretch about to begin, and asking it anything gets silence.
     */
    private const int REPLACED = 1;

    /**
     * How many open spans, lost tags and pinned facts are shown. A cap per section rather than one over
     * the whole block, so a session with forty open spans cannot crowd out the one lost tag — and the
     * newest are kept, since a span opened two hours ago is likelier abandoned than in flight.
     */
    private const int SPANS = 6;

    private const int LOST = 5;

    private const int PINS = 3;

    /**
     * How wide one filed line may read before it is clipped. An entry is already only the first line of
     * what was said — this bounds the pathological one so it cannot spend the section's whole budget.
     */
    private const int LINE = 130;

    /**
     * @param  int  $budget  bytes the whole block may spend, sections dropped worst-first to fit
     */
    public function __construct(
        private Reading $reading,
        private string $binary,
        private int $budget,
    ) {}

    /**
     * The block, built to fit. The footer is reserved first — it is what makes the rest readable as
     * measurement rather than as more summary — and the sections are then filled in the order they are
     * worth, each taken whole or not at all, since half a list of open work is a list that lies.
     */
    public function render(): string
    {
        $footer = $this->provenance();
        $left = $this->budget - strlen($footer);
        $body = '';

        foreach ([$this->open(), $this->lost(), $this->pinned()] as $section) {
            if ($section === '' || strlen($section) > $left) {
                continue;
            }

            $body .= $section;
            $left -= strlen($section);
        }

        return $body . $footer;
    }

    /**
     * The work the agent said it had started and never said it finished. This is the one thing in a
     * session that is live STATE rather than history, and the one thing no summariser can reconstruct:
     * it reads a conversation, and an open span is the absence of a sentence.
     */
    private function open(): string
    {
        $spans = array_map(
            fn (Entry $entry) => $entry->time() . '  ' . $entry->text,
            $this->reading->openWork(),
        );

        return $this->listing(
            'OPEN WORK — you said [!start] and never [!end]. Filed by the recorder as you spoke, not inferred. Say where each stands before you begin anything new',
            $spans,
            self::SPANS,
        );
    }

    /**
     * Tags that were said and never recorded. An agent cannot tell "I stopped tagging" from "I tagged and
     * the tool did not hear me" — from the inside both are silence — so it carries the loss as a belief
     * that it closed work it did not, which is exactly the belief a fresh summary makes unfalsifiable.
     */
    private function lost(): string
    {
        return $this->listing(
            'SAID BUT NEVER FILED — the record disagrees with what you said. Anything you believe you closed here is still open',
            $this->reading->unfiled(self::REPLACED),
            self::LOST,
        );
    }

    /**
     * The last few pins that STILL STAND, and only the last few. A pin a later one superseded is not
     * carried: this block is on a measured byte budget, and a fact that has been corrected may not spend
     * it — the struck one is kept in the record and read with `journal pins`, never handed to somebody
     * who would act on it. Each carries the time it was filed, because a pin states what was true when it
     * was written and nothing else in it says when that was. The full list does not belong here either:
     * it reaches the summariser through the compaction's own instructions, and the pins that survived a
     * real compaction did so by being rewritten INTO the summary rather than attached to the session that
     * woke from it.
     */
    private function pinned(): string
    {
        return $this->listing(
            'PINNED — still standing, each stamped with when it was measured. The rest is in the summary above, in its own words',
            array_map(fn (Entry $entry) => $entry->time() . '  ' . $entry->text, $this->reading->pinnedFacts()),
            self::PINS,
        );
    }

    /**
     * Where the rest of it is, and what the summary above is worth. Named plainly, because the summary
     * states its guesses in the same voice as its findings and nothing in it says which is which.
     */
    private function provenance(): string
    {
        return <<<TEXT


            The rest of the conversation is on disk and lost nothing. `{$this->binary} journal --back=1` is the stretch this summary replaced, `journal user` the user's own words in full.

            Everything above came from the record. Everything else you are reading is the SUMMARY — a paraphrase, and it states what was GUESSED in the same voice as what was measured. Treat any judgement in it as a belief until you have re-measured it, especially one about what another process or agent is doing.
            TEXT;
    }

    /**
     * A heading over its items, or nothing when there are none. The count is stated whenever some were
     * left out, so a truncated list reads as truncated rather than as the whole of it.
     *
     * @param  list<string>  $items
     */
    private function listing(string $heading, array $items, int $keep): string
    {
        if ($items === []) {
            return '';
        }

        $shown = array_slice($items, -$keep);
        $of = count($shown) < count($items) ? sprintf(' (%d of %d, newest last)', count($shown), count($items)) : '';
        $bullets = implode("\n", array_map(fn (string $item) => '  • ' . mb_strimwidth($item, 0, self::LINE, '…'), $shown));

        return "\n\n" . $heading . $of . ":\n" . $bullets;
    }
}
