<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration\Events;

/**
 * A project's own tie-in to one orchestration moment, written into `.commandments/custom/` and enrolled
 * by the by-file discovery its skills and detectors already load through
 * ({@see \JesseGall\CodeCommandments\Custom}), silenced by `$config->disable(...)` like any other rule.
 * WHICH moment it handles is the type its `handle(Accepting $event): Verdict` takes — {@see Event} itself
 * to hear every one — never a `handles()` list beside the method that could drift from the signature.
 */
abstract class Handler {}
