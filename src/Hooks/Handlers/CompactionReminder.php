<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Cli\Journal;
use JesseGall\CodeCommandments\Hooks\HookEvent;
use JesseGall\CodeCommandments\Support\Binary;

/**
 * A `SessionStart` hook for the ONE source that silently drops loaded skills: `compact`. Compaction
 * discards the full text of every skill the agent had loaded while the summary keeps the FEELING that
 * they are in effect, and it re-fires `SessionStart` with `source: compact`, so this catches that exact
 * moment — unconditionally, since every compaction genuinely drops them. It carries the RECOVERY with it
 * ({@see Journal\Recovery}) rather than the commands that would fetch one: telling a freshly compacted
 * agent to go and read three things was measured on a real compaction, and it read none of them.
 */
final class CompactionReminder extends Hook
{
    /**
     * The only SessionStart source that DROPS in-context skills — a fresh/forked/resumed session keeps them.
     */
    private const string COMPACT = 'compact';

    /**
     * How many bytes this injection may spend, and it is measured rather than chosen. A session that
     * compacted with its whole pin list attached sent 50,053 bytes; the harness spilled that to a file
     * and delivered a 2KB preview, so about 4% of what was sent was ever read. Past this size a block is
     * not delivered, it is merely emitted — which makes the budget the design constraint and not a
     * tidiness one: what goes in is CHOSEN, never concatenated.
     */
    private const int ARRIVES = 2_000;

    public function summary(): string
    {
        return "After a context compaction (which silently drops loaded skills), reminds you to reload any skill governing your current task.";
    }

    public function bindings(): array
    {
        return [new HookBinding('SessionStart')];
    }

    protected function onSessionStart(HookEvent $event): int
    {
        if ($event->source() !== self::COMPACT) {
            return $this->pass(); // startup/clear/resume/fork keep loaded skills in context — nothing dropped.
        }

        $reminder = $this->reminder();

        return $this->inject($event, $reminder . $this->recovered($event, self::ARRIVES - strlen($reminder)));
    }

    /**
     * The record, already read. The agent on this side has never seen the conversation — only a
     * paraphrase — and does not know what it is missing, so it is handed the two things the paraphrase
     * cannot hold and told where the rest is, inside whatever the reminder left of the budget.
     */
    private function recovered(HookEvent $event, int $budget): string
    {
        $session = new Journal\Session($event->sessionId(), $event->transcriptPath(), 0, '');

        return new Journal\Recovery(
            new Journal\Reading($session, $event->root),
            Binary::in($event->root),
            $budget,
        )->render();
    }

    /**
     * Kept short deliberately. Every character of prose here is a character the record cannot have, and
     * the record is the part that cannot be reconstructed from anywhere else.
     */
    private function reminder(): string
    {
        return "Code Commandments — the context was just COMPACTED. That dropped the full text of every skill you "
            . "had loaded while leaving the summary's impression that they still hold. Do not act on a remembered "
            . "paraphrase of a rule: RELOAD, through the Skill tool, every skill governing what you are doing now.";
    }
}
