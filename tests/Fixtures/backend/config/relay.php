<?php

// @example DeadConfigKey bad

use JesseGall\CodeCommandments\Sins\Backend\Laravel\DeadConfigKey;
use JesseGall\CodeCommandments\Testing\Sinful;

/*
 * The relay agent's settings. `heartbeat_seconds` is read by KioskSettings; the two below it were
 * left behind when the in-house builder was deleted, and nothing has read them since — but a new
 * author would reasonably assume they are wired to something.
 */
#[Sinful(DeadConfigKey::class)]
static fn (): null => null;

return [

    'heartbeat_seconds' => 30,

    'builder_url' => 'http://agent-builder:8080',

    'build_cache_retention_days' => 30,

];
