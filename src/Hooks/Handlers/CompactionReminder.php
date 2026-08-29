<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Cli\Journal;
use JesseGall\CodeCommandments\Hooks\HookEvent;
use JesseGall\CodeCommandments\Support\Binary;

/**
 * A `SessionStart` hook for the ONE source that silently drops loaded skills: `compact`. Context
 * compaction summarises the conversation and, in doing so, discards the full text of any skill the
 * agent had loaded — but the summary keeps the FEELING that they are in effect, so the agent goes on
 * acting from a half-remembered paraphrase of rules it no longer actually holds. Compaction re-fires
 * `SessionStart` with `source: compact`, so this catches that exact moment and injects a reminder to
 * RELOAD any skill governing the current task via the Skill tool, rather than trust a remembered
 * summary. Unconditional and unrate-limited on purpose: every compaction genuinely drops the skills,
 * and it is not tied to a plan or a config toggle — it should just always happen.
 */
final class CompactionReminder extends Hook
{
    /**
     * The only SessionStart source that DROPS in-context skills — a fresh/forked/resumed session keeps them.
     */
    private const string COMPACT = 'compact';

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

        return $this->inject($event, $this->reminder() . $this->journal($event));
    }

    /**
     * What the summary took, and how to get it back. This is the moment the journal exists for: the agent
     * on THIS side has never seen the conversation, only a paraphrase of it, and does not know what it is
     * missing — so it is told the pinned facts outright and pointed at the record for the rest.
     */
    private function journal(HookEvent $event): string
    {
        $binary = Binary::in($event->root);
        $reading = new Journal\Reading(new Journal\Session($event->sessionId(), $event->transcriptPath(), 0, ''), $event->root);
        $pinned = $reading->pinned();
        $open = $reading->open();

        $said = "\n\nThe summary you are reading kept what was DONE and lost what was DECIDED — the ruling the "
            . "user gave once, the approach you abandoned, the thing you were half-way through. The transcript "
            . "lost none of it. BEFORE your next substantive step:\n\n"
            . "  {$binary} journal --back=1   the stretch this summary replaced\n"
            . "  {$binary} journal user       the user's own words, in full";

        if ($pinned !== '') {
            $said .= "\n\nFacts pinned to survive this compaction:\n" . $pinned;
        }

        if ($open !== '') {
            $said .= "\n\nWork you had OPEN and never closed — say where it stands:\n" . $open;
        }

        return $said;
    }

    private function reminder(): string
    {
        return "Code Commandments — the context was just COMPACTED. Compaction rewrites the conversation into a "
            . "summary and, in doing so, drops the full instructions of any skill you had loaded — even though the "
            . "summary can make it feel like they are still in effect. Do NOT act on a remembered paraphrase of a "
            . "skill's rules. Before your next substantive step, RELOAD — re-invoke via the Skill tool — every skill "
            . "that governs what you're currently doing — the ones carrying the cardinal rules for this task — so you "
            . "are working from their actual text again, not a faded summary of it.";
    }
}
