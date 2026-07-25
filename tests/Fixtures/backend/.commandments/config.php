<?php

use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Detectors\Backend\NamespaceDependencyDetector;

/*
 * The Shop's own project configuration — the fixture is a project, so it declares here what only a
 * project can know. Read the stack top-down: Pages are built from Shared, Shared from Elements,
 * Elements from nothing above them. Every namespace NOT listed (the rest of the Shop, the
 * framework, this package's own test attributes) is unconstrained, in both directions.
 */
return function (Config $config): void {
    $config->configure(fn (NamespaceDependencyDetector $detector) => $detector
        ->layer('Shop\\Ui\\Elements')
        ->layer('Shop\\Ui\\Shared', mayUse: ['Shop\\Ui\\Elements'])
        ->layer('Shop\\Ui\\Pages', mayUse: ['Shop\\Ui\\Shared', 'Shop\\Ui\\Elements']));
};
