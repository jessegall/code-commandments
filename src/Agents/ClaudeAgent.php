<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Agents;

use JesseGall\CodeCommandments\Hooks\HookRegistry;

/**
 * Claude Code. The one agent with a hook protocol, so it is the only one where the disciplines are
 * ENFORCED rather than merely written down — the cardinal-rule heartbeat, the judge nudge, the
 * keep-going gate. It is also the one agent that does not read the shared `AGENTS.md`, so it gets a
 * file of its own that imports the canon.
 */
final class ClaudeAgent extends Agent
{
    /**
     * The block name is FROZEN, and it is history rather than live text: every project already
     * wired carries these exact markers around the old briefing in its `CLAUDE.md`. Reusing them is
     * the entire migration — the long block is replaced, in place, by the pointer below, and there
     * is no separate upgrade step to run or to get wrong.
     */
    public const string BLOCK = 'code-commandments skills';

    public function name(): string
    {
        return 'Claude Code';
    }

    public function summary(): string
    {
        return 'skills, `CLAUDE.md` (imports `AGENTS.md`), and the hooks — the only agent whose disciplines are enforced rather than only written down';
    }

    public function skillsDir(): ?string
    {
        return '.claude/skills';
    }

    public function commandsDir(): ?string
    {
        return '.claude/commands';
    }

    public function instructionsFile(): ?string
    {
        return 'CLAUDE.md';
    }

    public function blockName(): string
    {
        return self::BLOCK;
    }

    /**
     * An import of the canon, then what is true of THIS agent alone: the names its harness gives
     * the briefing's ideas, and the hooks that enforce them. `CLAUDE.md` is the file Claude Code
     * reads, and the import is what carries the shared briefing into it.
     */
    public function instructions(): string
    {
        return <<<'MD'
        @AGENTS.md

        ## Working here as Claude Code

        The briefing above is the canon, shared with every agent. These are the parts of it
        that have a specific name in this harness:

        - **Load a skill with the Skill tool**, by the exact id in the briefing's bullets —
          e.g. `commandments-backend-absence`. The published skills are linked into
          `.claude/skills/`, so they also autocomplete as `/`-commands.
        - **The visible to-do list is `TodoWrite`.** Mirror every `until` condition into it,
          mark an item completed the moment you strike its condition off, and keep the item
          you are working on FIRST — the list is checked, and you will be sent back to
          reorder it when its first line does not say what you are doing right now.
        - **Never delegate a write to a subagent.** Dispatch them for read-only work as much
          as you like; every edit is yours.
        - `/until "<condition>"` is available as a slash command, so the user can set a stop
          condition themselves.

        **The disciplines here are ENFORCED, not just written down.** Hooks are wired into
        `.claude/settings.json`: the cardinal rule resurfaces as you work, `judge` is nudged
        before risky commands and on stop, an approved plan is ground to completion, and a
        standing `until` condition holds every stop until you have VERIFIED it. That is a
        property of this agent alone — under an agent with no hook protocol the same
        disciplines are documents you are asked to follow, and nothing checks that you did.
        MD;
    }

    public function ignored(): array
    {
        return [
            '# code-commandments published skills (regenerated on composer update)' => '.claude/skills/commandments-*',
            '# code-commandments published slash commands (regenerated on composer update)' => '.claude/commands/until.md',
        ];
    }

    public function enforces(): bool
    {
        return true;
    }

    public function wire(string $root): bool
    {
        return HookRegistry::wire($root, HookRegistry::forProject($root));
    }
}
