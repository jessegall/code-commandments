<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Agents;

/**
 * Codex. It declares almost nothing because it reads both the library and the canon where they
 * already are: `.agents/skills` is where it looks for a project's skills, `AGENTS.md` the file it
 * reads. Nothing to link, nothing to point at the canon — and, lacking a hook protocol, the
 * disciplines reach it as documents it is asked to follow rather than a harness that holds it.
 */
final class CodexAgent extends Agent
{
    public function name(): string
    {
        return 'Codex';
    }

    public function summary(): string
    {
        return 'skills and `AGENTS.md`, both read where they already live — no links, no hooks';
    }
}
