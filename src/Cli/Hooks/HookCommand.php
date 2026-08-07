<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Hooks;

use JesseGall\CodeCommandments\Hooks\Hook;


use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Doc\Summary;
use JesseGall\CodeCommandments\Cli\Help\Help;
use ReflectionClass;
use JesseGall\CodeCommandments\Cli\Kernel;
use JesseGall\CodeCommandments\Cli\Input;
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

    /**
     * Projected from the {@see Hook}'s OWN docblock ({@see Summary::first}) — the hook explains itself
     * where it is written, so wiring one as a verb documents it too, with nothing restated here.
     */
    public function help(): Help
    {
        return Help::of(Summary::first(new ReflectionClass($this->hook)->getDocComment()))
            ->form($this->names[0], 'run this hook — it reads its payload from stdin, not from flags')
            ->section(Help::HOOKS);
    }

    public function run(Input $input): int
    {
        return $this->hook->run($input->raw());
    }
}
