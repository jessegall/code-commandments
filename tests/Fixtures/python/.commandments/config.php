<?php

use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Detectors\Python\NamespaceDependencyDetector;

/*
 * The Python shop's own project configuration — the fixture is a project, so it declares here what only a
 * project can know. Read the stack top-down: screens are built from widgets and the layout, widgets from the
 * layout, and the layout from nothing at all. The menus may use the layout. Every package NOT listed is
 * unconstrained, both ways.
 */
return function (Config $config): void {
    $config->configure(fn (NamespaceDependencyDetector $detector) => $detector
        ->layer('shop.layout')
        ->layer('shop.widgets', mayUse: ['shop.layout'])
        ->layer('shop.screens', mayUse: ['shop.widgets', 'shop.layout'])
        ->layer('shop.menus', mayUse: ['shop.layout']));
};
