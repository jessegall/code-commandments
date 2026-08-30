<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\CodeCommandments\Hooks\AgentSettings;
use JesseGall\CodeCommandments\Support\File;
use JesseGall\CodeCommandments\Workspace;

/**
 * The world one agent runs in, and all of it: a directory holding its settings and nothing else. An agent
 * handed a PROJECT's world spends its first turns reading a repository nobody asked it about and its last
 * ones being pushed to continue by hooks nobody meant it to have. A project cannot change what is in
 * here — what an agent hears is derived from which hooks are MARKED for its kind — so two agents of a
 * kind are alike wherever they run.
 */
final readonly class World
{
    private const string FOLDER = 'worlds';

    public function __construct(
        private Workspace $workspace,
        private string $root,
        private string $agent,
        private bool $assistant,
    ) {}

    /**
     * A world for a WORKER — one dispatched to do a piece of work and report. It hears the rules about
     * the code it is writing and nothing about running a build.
     */
    public static function forWorker(Workspace $workspace, string $root, string $agent): self
    {
        return new self($workspace, $root, $agent, assistant: false);
    }

    /**
     * A world for an ASSISTANT — one a profile named, which persists across dispatches and keeps a
     * record. It hears the journal bookkeeping too, because somebody reads that back.
     */
    public static function forAssistant(Workspace $workspace, string $root, string $agent): self
    {
        return new self($workspace, $root, $agent, assistant: true);
    }

    public function path(): string
    {
        return $this->workspace->path(self::FOLDER . '/' . $this->agent);
    }

    /**
     * Stand it up, and answer whether it is there. Written fresh each time rather than reused: the
     * settings are DERIVED from which hooks are marked today, so a world prepared last week would carry
     * last week's answer and nothing in it would say so.
     */
    public function prepare(): bool
    {
        return new AgentSettings($this->root)->writeInto($this->path(), $this->assistant)
            && File::write($this->path() . '/README.md', $this->explains());
    }

    /**
     * Why the folder exists, for whoever finds it. A directory nobody can account for is one somebody
     * deletes at the wrong moment or, worse, starts putting things in.
     */
    private function explains(): string
    {
        $kind = $this->assistant ? 'assistant' : 'worker';

        return <<<TEXT
            # {$this->agent}

            The whole world the `{$this->agent}` {$kind} runs in. It holds settings and nothing else, so
            the agent inherits no project instructions, no skills, and no hooks but the few it needs — it
            is told what to do and it does that, rather than reading a repository first.

            `Stop` is deliberately absent: a dispatched agent's stop IS its completion, and a hook that
            holds it can only push it to speak again.

            Nothing here is edited by hand, and a project cannot configure it. It is rewritten whenever an
            agent is started, from which hooks are marked for its kind. Safe to delete.
            TEXT;
    }
}
