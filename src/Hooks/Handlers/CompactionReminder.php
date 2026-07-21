<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;

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
    /** The only SessionStart source that DROPS in-context skills — a fresh/forked/resumed session keeps them. */
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

        return $this->inject($event, $this->reminder());
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
