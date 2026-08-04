<?php

namespace Shop\Ui\Elements;

use JesseGall\CodeCommandments\Sins\Backend\NamespaceDependency;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Ui\Shared\Panel;
use Shop\Ui\Tokens\Accent;

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

    /**
     * The FIX: the arrow inverted. The badge hands out only what it owns — a token from the layer
     * BELOW it — and Shared assembles the panel from that. Elements names nothing above itself, so it
     * reads, tests and moves on its own again.
     */
    #[Fixed(NamespaceDependency::class)]
    public function accent(): Accent
    {
        return new Accent($this->label);
    }
}
