<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks;

use JesseGall\CodeCommandments\Support\Binary;
use JesseGall\CodeCommandments\Support\File;

/**
 * The settings a spawned agent runs under — its own, never the orchestrator's. An agent handed the
 * project's settings inherits every hook wired for the session that spawned it, and one that HOLDS a stop
 * is fatal there: it tries to finish, the hold pushes it on, it says "waiting", the hold fires again.
 * That is a token furnace and it has been watched happening.
 */
final readonly class AgentSettings
{
    private const string FILE = 'settings.json';

    /**
     * The moment a spawned agent NEVER hears, whatever its hooks say. Its stop is its completion — it has
     * one job and finishing is the whole of it — so a hook that holds a stop can only push it to speak
     * again, and it answers "waiting", and is pushed again. Nothing decides this per hook because there
     * is no agent for which holding a worker's stop is right.
     */
    private const string NEVER = 'Stop';

    public function __construct(private string $root) {}

    /**
     * Write settings into $configDir — what the agent is handed as `CLAUDE_CONFIG_DIR`. An ASSISTANT is
     * one the profile named, which keeps a record across dispatches, so it hears the journal bookkeeping
     * too; anything else hears the code disciplines alone.
     */
    public function writeInto(string $configDir, bool $assistant): bool
    {
        $wired = [];

        foreach ($this->momentsFor($assistant) as $event) {
            $wired[$event] = [[
                'hooks' => [[
                    'type' => 'command',
                    'command' => sprintf('php %s hooks # @code-commandments-managed', escapeshellarg(Binary::in($this->root))),
                ]],
            ]];
        }

        return File::write(
            rtrim($configDir, '/') . '/' . self::FILE,
            json_encode(['hooks' => $wired], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        );
    }

    /**
     * The moments an agent of this kind can hear, DERIVED from what its hooks bind to rather than listed.
     * `Stop` is absent for a worker not because it is filtered but because nothing it hears binds to it —
     * structural silence rather than remembered silence, so a hook marked tomorrow reaches it that day
     * and a moment stops being wired the day nothing needs it.
     *
     * @return list<string>
     */
    public function momentsFor(bool $assistant): array
    {
        $events = [];

        foreach (HookRegistry::BUILTINS as $class) {
            if (! is_subclass_of($class, Discipline::class)) {
                continue;
            }

            if (! $assistant && is_subclass_of($class, ForAssistants::class)) {
                continue;
            }

            foreach (new $class()->bindings() as $binding) {
                if ($binding->event !== self::NEVER) {
                    $events[$binding->event] = true;
                }
            }
        }

        return array_keys($events);
    }
}
