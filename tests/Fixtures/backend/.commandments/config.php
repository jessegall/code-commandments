<?php

use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Detectors\Backend\NamespaceDependencyDetector;

/*
 * The Shop's own project configuration — the fixture is a project, so it declares here what only a
 * project can know. Read the stack top-down: Pages are built from Shared, Shared from Elements,
 * Elements from the raw tokens, and the tokens from nothing at all. Every namespace NOT listed (the
 * rest of the Shop, the framework, this package's own test attributes) is unconstrained, both ways.
 */
return function (Config $config): void {
    $config->configure(fn (NamespaceDependencyDetector $detector) => $detector
        ->layer('Shop\\Ui\\Tokens')
        ->layer('Shop\\Ui\\Elements', mayUse: ['Shop\\Ui\\Tokens'])
        ->layer('Shop\\Ui\\Shared', mayUse: ['Shop\\Ui\\Elements', 'Shop\\Ui\\Tokens'])
        ->layer('Shop\\Ui\\Pages', mayUse: ['Shop\\Ui\\Shared', 'Shop\\Ui\\Elements', 'Shop\\Ui\\Tokens']));
};
