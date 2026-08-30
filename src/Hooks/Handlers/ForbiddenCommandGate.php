<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Cli\Orchestration\Profiles;
use JesseGall\CodeCommandments\Hooks\Discipline;
use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;

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
     * Does this command RUN the forbidden one, rather than merely mention it? Asked of each segment the
     * shell would execute, because a command that only contains the words is a different thing: the
     * first version of this gate refused an `echo` that quoted the command it was testing for, which is
     * how a check earns a reputation for firing on nothing and gets worked around instead of heeded.
     */
    private function runs(string $command, string $forbidden): bool
    {
        foreach ($this->segments($command) as $segment) {
            if (str_starts_with($segment, $forbidden)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The command split at the points a shell would start a new one, each trimmed of the whitespace and
     * grouping that carry no meaning for what is about to run.
     *
     * A BACKTICK is not one of those points, though the shell would treat it as one. Backticks are how
     * prose quotes a command, so counting them refused this gate's own commit message for naming what it
     * forbids — and a rule that cannot be written about is one nobody can explain. `$(` stays, being
     * substitution and nothing else.
     *
     * @return list<string>
     */
    private function segments(string $command): array
    {
        $segments = [];

        foreach (explode("\n", str_replace(['&&', '||', ';', '|', '$('], "\n", $this->unquoted($command))) as $segment) {
            $segments[] = ltrim(trim($segment), '({ ');
        }

        return $segments;
    }

    /**
     * The command with the CONTENTS of every quoted string blanked out, the quotes themselves kept so the
     * shape around them survives. A separator inside quotes is not a separator — a shell would never
     * start a command there — and the two earlier versions of this gate both got that wrong in different
     * costumes: first by matching anywhere, then by treating any `;` or `&&` as a boundary wherever it
     * appeared. Both fired on PROSE ABOUT THE RULE, three times between two sessions, including on the
     * commit messages describing the feature.
     *
     * That is worse than an ordinary false positive. A refusal that fires on writing ABOUT a command
     * teaches people to rephrase until it stops, and an agent that has learned to rephrase past one
     * refusal has learned it about all of them.
     */
    private function unquoted(string $command): string
    {
        $out = '';
        $quote = '';

        foreach (str_split($command) as $char) {
            if ($quote === '' && ($char === '"' || $char === "'")) {
                $quote = $char;
                $out .= $char;

                continue;
            }

            if ($quote !== '' && $char === $quote) {
                $quote = '';
                $out .= $char;

                continue;
            }

            $out .= $quote === '' ? $char : ' ';
        }

        return $out;
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
