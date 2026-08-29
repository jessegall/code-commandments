<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Journal;

use JesseGall\CodeCommandments\Cli\Text;
use JesseGall\CodeCommandments\Workspace;

/**
 * The ways one session can be read — the stretch since a compaction, the last few messages, the user's own
 * words, a search, the pins, the work left open. Asked for by a person through the {@see Menu} and by an
 * agent through the {@see JournalCommand}, which is why they live here rather than once in each.
 */
final readonly class Reading
{
    /**
     * How many characters a reading spends when nobody asked for the whole thing. A digest read after a
     * compaction is paid for in the very context it exists to restore, so it is bounded by default — and a
     * person at a terminal, who is spending their own scrollback and not a budget, passes none.
     */
    public const int BUDGET = 16000;

    /**
     * @param  ?int  $budget  characters to fit into, or none for the whole thing
     */
    public function __construct(
        private Session $session,
        private string $root,
        private ?int $budget = self::BUDGET,
    ) {}

    /**
     * The conversation $back compactions ago — 0 being the stretch since the last one.
     */
    public function since(int $back): string
    {
        return new Digest($this->session->transcript()->chunk($back))->render($this->budget);
    }

    /**
     * The last $count things anybody said. What a person wants on opening the journal: not the session, the
     * place they left off.
     */
    public function recent(int $count): string
    {
        return new Digest(array_slice($this->spoken(), -$count))->render($this->budget);
    }

    /**
     * Only the user, in full. Their words are what a summary loses first.
     */
    public function said(): string
    {
        return new Digest(array_values(array_filter($this->spoken(), fn (Line $line) => $line->isPrompt())))->render($this->budget);
    }

    public function mentioning(string $term): string
    {
        if ($term === '') {
            return '';
        }

        return new Digest(array_values(array_filter($this->spoken(), fn (Line $line) => $line->mentions($term))))->render($this->budget);
    }

    /**
     * The facts pinned to outlive every compaction. They come from the session's own INDEX rather than its
     * transcript: a pin is recorded through the command, never said in a message, so the transcript never
     * saw it. $last shows only the most recent, for a list long enough that a reader would otherwise tail
     * it and miss the middle.
     */
    public function pinned(?int $last = null): string
    {
        return $this->listed($this->journal()->pinned(), 'pinned facts', $last);
    }

    /**
     * Work started and never closed — the one thing in a session that is live state rather than history.
     */
    public function open(): string
    {
        return $this->listed($this->journal()->openSpans(), 'work left open');
    }

    /**
     * Does the index agree with what was actually SAID? An agent cannot tell "I stopped tagging" from "I
     * tagged and the tool did not hear me" — from the inside those are the same silence, and the second
     * one leaves it believing it closed work it did not. So the two records are compared: the transcript
     * holds every word, the index holds what was filed, and a tag in the first that is missing from the
     * second is a recording loss the agent would otherwise carry as a false belief for hours.
     */
    public function verify(): string
    {
        $said = $this->tagsSaid();
        $filed = $this->tagsFiled();
        $lost = array_values(array_diff($said, $filed));

        if ($said === [] && $this->agentSpeech() === []) {
            return 'You have not spoken in this stretch yet, so there is nothing to check. Tag your work as '
                . 'you go and run this again.';
        }

        if ($said === []) {
            return <<<TEXT
                You have spoken in this stretch and tagged nothing — so there is nothing to check, and no
                record of what you started or decided.

                If you HAVE been tagging, the recorder is not hearing you: check that `MessageDisplay` is
                wired, and that you are not reading a different session's record than the one you spoke into.
                TEXT;
        }

        if ($lost === []) {
            return sprintf('The record agrees: all %d tagged line(s) were filed.', count($said));
        }

        $heading = Text::heading(sprintf('NOT FILED (%d of %d)', count($lost), count($said)));
        $lines = implode("\n", array_map(fn (string $line) => '  • ' . Text::wrap($line, 4), $lost));

        return <<<TEXT
            {$heading}

            You said these and the journal never recorded them. Anything you believe you closed here is
            still open, and anything you believe you pinned was not.

            {$lines}
            TEXT;
    }

    /**
     * Every tagged line the transcript holds for this stretch — what was said.
     *
     * @return list<string>
     */
    private function tagsSaid(): array
    {
        $said = [];

        foreach ($this->agentSpeech() as $line) {
            foreach (Tag::taggedLines($line->text) as [, $tagged]) {
                $said[] = $tagged;
            }
        }

        return $said;
    }

    /**
     * The agent's own lines in this stretch. A stretch it has not spoken in yet is silent for a reason
     * nothing is wrong with — which is why the tag check has to tell that apart from a recorder that is
     * not hearing it.
     *
     * @return list<Line>
     */
    private function agentSpeech(): array
    {
        $spoken = [];

        foreach ($this->session->transcript()->chunk() as $line) {
            if ($line->isSpeech() && ! $line->isPrompt()) {
                $spoken[] = $line;
            }
        }

        return $spoken;
    }

    /**
     * Every tagged line the index holds — what was filed.
     *
     * @return list<string>
     */
    private function tagsFiled(): array
    {
        $filed = [];

        foreach ($this->journal()->entries() as $entry) {
            if ($entry->tag->isSome()) {
                $filed[] = $entry->text;
            }
        }

        return $filed;
    }

    /**
     * Everything said in the current stretch, the machinery around it dropped.
     *
     * @return list<Line>
     */
    private function spoken(): array
    {
        return Digest::spokenIn($this->session->transcript()->chunk());
    }

    /**
     * The index this session kept beside its transcript — which is reachable for ANY session, since the
     * folder is named after the session's own id.
     */
    private function journal(): Journal
    {
        return Journal::inSession(new Workspace($this->root, $this->session->id));
    }

    /**
     * A numbered, wrapped list with air between the items. These are paragraphs a person weighs one at a
     * time, not a set of labels — run together they read as one wall and none of them is found.
     *
     * @param  list<Entry>  $entries
     */
    private function listed(array $entries, string $title, ?int $last = null): string
    {
        if ($entries === []) {
            return '';
        }

        $shown = $last === null ? $entries : array_slice($entries, -$last);
        $heading = $title . ' (' . count($entries) . ')' . (count($shown) < count($entries) ? ", last {$last}" : '');
        $lines = [Text::heading($heading), ''];

        foreach ($shown as $at => $entry) {
            $lines[] = sprintf('%2d  %s', $at + 1, Text::wrap($entry->text, 4));
            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
