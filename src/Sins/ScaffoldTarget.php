<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins;

/**
 * Where a {@see Scaffold} is written — which source root, and whether a namespace is injected.
 * A closed set the `scaffold` command resolves; grow it by adding a case (a new engine/root),
 * never a bare boolean flag.
 */
enum ScaffoldTarget: string
{
    /** The PHP PSR-4 source root — the consumer's namespace is injected into the stub. */
    case Backend = 'backend';

    /** The JS source root — a Vue component, no namespace to inject. */
    case Frontend = 'frontend';
}
