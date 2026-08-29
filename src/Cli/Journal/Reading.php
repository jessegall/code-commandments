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
     * saw it.
     */
    public function pinned(): string
    {
        return $this->listed($this->journal()->pinned(), 'pinned facts');
    }

    /**
     * Work started and never closed — the one thing in a session that is live state rather than history.
     */
    public function open(): string
    {
        return $this->listed($this->journal()->openSpans(), 'work left open');
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
    private function listed(array $entries, string $title): string
    {
        if ($entries === []) {
            return '';
        }

        $lines = [Text::heading($title . ' (' . count($entries) . ')'), ''];

        foreach ($entries as $at => $entry) {
            $lines[] = sprintf('%2d  %s', $at + 1, Text::wrap($entry->text, 4));
            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
