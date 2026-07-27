<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Help;

/**
 * One way to invoke a command — the syntax as the user types it (without the `commandments` prefix,
 * which the renderer adds) and what that form does. A command with subcommands declares one form per
 * subcommand, so the help screen IS the grammar and there is nothing to keep in sync by hand.
 */
final class Form
{
    public function __construct(
        public readonly string $syntax,
        public readonly string $does = '',
    ) {}
}
