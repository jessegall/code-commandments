<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks;


use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Input;
/**
 * `commandments hooks` — the one entry point every wired hook moment runs through. Reads the payload
 * once, runs every registered handler ({@see HookRegistry::forProject}), and merges their responses
 * into one ({@see HookResponse}). Each handler self-routes by event and stays silent when the moment
 * isn't its concern.
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

        foreach (HookRegistry::forProject($event->root) as $class) {
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
