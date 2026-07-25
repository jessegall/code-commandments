<?php

namespace Shop\Ui\Elements;

use JesseGall\CodeCommandments\Sins\Backend\NamespaceDependency;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Ui\Shared\Panel;

/**
 * A badge — one of the UI primitives everything else is built from.
 */
final class Badge
{
    public function __construct(public readonly string $label) {}

    /**
     * The arrow pointing back up: Elements is the bottom of the stack, yet this one hands out a
     * Panel — the thing Shared assembles FROM badges. Elements can no longer be read, reused or
     * moved without Shared coming along.
     */
    #[Sinful(NamespaceDependency::class)]
    public function inPanel(): Panel
    {
        return new Panel($this->label);
    }
}
