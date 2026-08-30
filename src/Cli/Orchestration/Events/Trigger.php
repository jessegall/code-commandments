<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration\Events;

/**
 * A build's own tie-in to one orchestration moment, in its PROFILE's `triggers/` folder and loaded only
 * while that profile is in force — where a {@see \JesseGall\CodeCommandments\Hooks\Hook} binds a
 * harness transport and states a fact about the PROJECT, so the scope is the whole difference. WHICH
 * moment it arms on is the type its `fire(Accepting $moment): Verdict` takes ({@see Event} itself to hear
 * every one), never a list beside the method that could drift from the signature.
 */
abstract class Trigger {}
