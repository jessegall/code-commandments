<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks;


use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Input;
/**
 * `commandments hook <FQCN>` — the one entry point every wired {@see Hook} runs through. {@see Hooks}
 * wires each hook (built-in or consumer-registered) as `hook '<class>'`, and this instantiates that
 * class and runs it. A consumer's own hook class autoloads because the CLI runs from the consumer's
 * vendor. Guards the class name (must be a real {@see Hook}) so a stale/mistyped wiring fails
 * cleanly with a message, never a fatal.
 */
final class HookRunner implements Command
{
    public function names(): array
    {
        return ['hook'];
    }

    public function run(Input $input): int
    {
        $class = ltrim((string) ($input->firstArgument() ?? ''), '\\');

        if ($class === '' || ! class_exists($class) || ! is_subclass_of($class, Hook::class)) {
            fwrite(STDERR, "commandments hook: '{$class}' is not a runnable " . Hook::class . ".\n");

            return 2;
        }

        return new $class()->run($input->raw());
    }
}
