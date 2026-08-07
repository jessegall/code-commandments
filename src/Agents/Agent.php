<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Agents;

/**
 * One coding assistant this package wires itself into. The disciplines are the same whichever
 * assistant reads them; what differs is only where that assistant LOOKS, which is all an agent
 * states — so supporting a new one is a class, and a project writes its own under
 * `.commandments/custom/` beside its own skills and detectors.
 */
abstract class Agent
{
    /**
     * How the agent is named to a human — in the console line that says what was wired, and in the
     * generated support table.
     */
    abstract public function name(): string;

    /**
     * One line for the README's agents table: what this agent gets from us.
     */
    abstract public function summary(): string;

    /**
     * Where this agent discovers project skills, relative to the project root — the folder that gets
     * a link per published skill. Null when it reads {@see \JesseGall\CodeCommandments\Workspace::LIBRARY}
     * itself and so needs no link of its own.
     */
    public function skillsDir(): ?string
    {
        return null;
    }

    /**
     * Where this agent reads user-invoked commands from, relative to the project root. Null when it
     * has no such notion.
     */
    public function commandsDir(): ?string
    {
        return null;
    }

    /**
     * The file this agent reads instructions from, relative to the project root — but ONLY when it
     * cannot read the shared `AGENTS.md` every other agent uses. Null (the default) means it reads
     * the canon, which is the whole point of publishing one.
     */
    public function instructionsFile(): ?string
    {
        return null;
    }

    /**
     * The name of the managed block this agent's own instructions file carries. It must differ from
     * the canon's, so that if the two files ever turn out to be one file the blocks cannot overwrite
     * each other.
     */
    public function blockName(): string
    {
        return '';
    }

    /**
     * What goes in that block — for an agent that cannot read the canon, a pointer to it plus
     * whatever guidance is true only of this agent.
     */
    public function instructions(): string
    {
        return '';
    }

    /**
     * The `.gitignore` entries this agent's artifacts need, each keyed by the comment that explains
     * it. Everything we publish is regenerated on every install, so none of it is a project's source.
     *
     * @return array<string, string>
     */
    public function ignored(): array
    {
        return [];
    }

    /**
     * Anything else this agent needs wired into the project — hooks, settings — done once per sync.
     * Returns whether it changed something. Most agents have nothing here: a hook protocol is a
     * thing an assistant either offers or doesn't.
     */
    public function wire(string $root): bool
    {
        return false;
    }
}
