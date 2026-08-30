<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks;

/**
 * A hook for agents that KEEP something — the orchestrator, and the assistants a profile names as roles.
 * Those have a record across dispatches, so declaring work and tagging what a message carries earns its
 * keep: somebody reads it back later, and after a compaction it is all that survives.
 *
 * A one-shot worker keeps nothing. It is dispatched, it does one thing, and it reports — its whole record
 * IS its report, and asking it to open a span before every write is bookkeeping nobody will ever read,
 * charged to the agent least able to spare the attention.
 *
 * Narrower than {@see Discipline}, which reaches every agent writing code because the rules about the
 * code are true whoever is holding it.
 */
interface ForAssistants extends Discipline {}
