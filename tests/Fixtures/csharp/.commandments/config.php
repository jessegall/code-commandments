<?php

use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Detectors\CSharp\NamespaceDependencyDetector;

/*
 * The C# shop's own project configuration — the fixture is a project, so it declares here what only a project
 * can know. Read the stack bottom-up: the catalog uses nothing, search and checkout are built on the catalog,
 * and the storefront on search and the catalog. Every namespace NOT listed is unconstrained, both ways.
 */
return function (Config $config): void {
    $config->configure(fn (NamespaceDependencyDetector $detector) => $detector
        ->layer('Shop.Catalog')
        ->layer('Shop.Search', mayUse: ['Shop.Catalog'])
        ->layer('Shop.Checkout', mayUse: ['Shop.Catalog'])
        ->layer('Shop.Storefront', mayUse: ['Shop.Search', 'Shop.Catalog']));
};
