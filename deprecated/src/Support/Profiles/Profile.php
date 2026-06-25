<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Profiles;

/**
 * A work profile — the top-level switch for how intrusive code-commandments is.
 *
 * Each concrete profile is a thin class returning a constant {@see ProfileOptions};
 * the options drive everything. Adding a profile = drop a subclass and register
 * it in {@see ProfileRegistry}. Hooks are DERIVED from the options (never
 * enumerated per profile) so a new profile can't drift from what the installer
 * knows how to emit.
 */
abstract class Profile
{
    /** The slug used on the CLI and stored in `.commandments/profile`. */
    abstract public function name(): string;

    /** One line shown by `profile list`. */
    abstract public function description(): string;

    abstract public function options(): ProfileOptions;
}
