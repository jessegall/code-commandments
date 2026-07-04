<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * `commandments hooks` — the ONE entry point every wired hook moment runs through. {@see Hooks} wires a
 * single stamped `hooks` command per moment (PostToolUse, Stop, PreToolUse/Bash, …); this reads the
 * payload once, runs EVERY registered handler ({@see Hooks::forProject} — the builtins plus a consumer's
 * `$config->hook(...)`), and merges their responses into one ({@see HookResponse}).
 *
 * A handler self-routes by event ({@see Hook::handle}) and stays silent when the moment isn't its
 * concern, so most runs emit nothing and Claude Code simply continues. The win: new hook behaviour is a
 * line in the registry, never a new settings entry — the wiring is written once (on install / `composer
 * update` sync) and never grows.
 */
final class HookDispatch implements Command
{
    public function __construct(private readonly HookIO $io = new HookIO) {}

    public function names(): array
    {
        return ['hooks'];
    }

    public function run(Input $input): int
    {
        $payload = $this->io->payload();
        $event = new HookEvent($payload, $this->io->projectRoot());
        $recorder = new RecordingHookIO($payload, $this->io->git());

        foreach (Hooks::forProject($event->root) as $class) {
            if (is_subclass_of($class, Hook::class)) {
                new $class($recorder)->run([]);
            }
        }

        $merged = HookResponse::merge($recorder->emitted, $event->name());

        if ($merged !== null) {
            $this->io->emit($merged);
        }

        return 0;
    }
}
