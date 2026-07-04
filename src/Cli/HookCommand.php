<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * Adapts a {@see Hook} to a {@see Command} so the reminder verbs (`remind`, `judge-reminder`,
 * `plan-reminder`) dispatch through the same registry as every other command. A Hook reads its
 * payload from stdin, not from flags, so the adapter simply forwards — but it lets the Kernel stay
 * uniform: one registry, one dispatch, no special case for hooks.
 */
final class HookCommand implements Command
{
    /**
     * @param  list<string>  $names
     */
    public function __construct(private readonly array $names, private readonly Hook $hook) {}

    public function names(): array
    {
        return $this->names;
    }

    public function run(Input $input): int
    {
        return $this->hook->run($input->raw());
    }
}
