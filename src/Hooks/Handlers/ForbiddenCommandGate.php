<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Cli\Orchestration\Profiles;
use JesseGall\CodeCommandments\Hooks\Discipline;
use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;
use JesseGall\CodeCommandments\Hooks\ShellCommand;

/**
 * Refuses a command the profile forbids. A {@see Discipline}, so it reaches every worker: a rule about
 * what may be RUN is true whoever is holding the shell, and a dispatched agent has less context about
 * what a command costs here than the session that dispatched it.
 */
final class ForbiddenCommandGate extends Hook implements Discipline
{
    public function summary(): string
    {
        return 'Refuses a shell command the profile in force has forbidden.';
    }

    public function bindings(): array
    {
        return [new HookBinding('PreToolUse', 'Bash')];
    }

    /**
     * Refuses rather than warns. A command that is forbidden because it can silently destroy or hide
     * work cannot be answered by telling somebody about it afterwards — which is the whole difference
     * between this and a reminder.
     */
    protected function onPreToolUse(HookEvent $event): int
    {
        $command = $event->command();

        if ($command === '') {
            return $this->pass();
        }

        foreach (Profiles::inForce($event->sessionWorkspace()) as $profile) {
            foreach ($profile->forbidden() as $forbidden) {
                if ($this->runs($command, $forbidden)) {
                    return $this->refuse($forbidden);
                }
            }
        }

        return $this->pass();
    }

    /**
     * Does this command RUN the forbidden one, rather than merely mention it? Asked of each command the
     * shell would actually start, because a command that only contains the words is a different thing:
     * the first version of this gate refused an `echo` that quoted the command it was testing for, which
     * is how a check earns a reputation for firing on nothing and gets worked around instead of heeded.
     *
     * What a shell would run is {@see ShellCommand}'s to say, not this gate's. Two gates each answering
     * it for themselves is how one of them learned about quoted strings and the other did not.
     */
    private function runs(string $command, string $forbidden): bool
    {
        foreach (ShellCommand::of($command)->invocations() as $invocation) {
            if ($invocation->runs($forbidden)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Names what was matched, not merely that something was. A refusal a reader cannot act on is one
     * they work around by rephrasing until it stops firing.
     */
    private function refuse(string $forbidden): int
    {
        return $this->block(sprintf(
            'Code Commandments — `%s` is forbidden by the profile in force, so this command will not '
                . 'run. It is listed under `forbid` in the profile\'s `settings.json`, which is where it '
                . 'is lifted if the project decides otherwise. Do the work another way rather than '
                . 'rephrasing the command past the check.',
            $forbidden,
        ));
    }
}
