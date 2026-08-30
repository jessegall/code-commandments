<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration\Events;

/**
 * A moment that has NOT happened yet, raised in the process about to make it happen — a `PreToolUse`
 * before the tool runs, or a command before it writes the board — and so the only shape a handler may
 * refuse, since everything else is already true when a handler sees it and refusing it would be theatre.
 * The difference is carried by the TYPE rather than a flag a subclass could forget: a refusal stands only
 * on one of these, and is demoted to a quiet note anywhere else ({@see Handlers::dispatch}).
 */
abstract readonly class Vetoable extends Event {}
