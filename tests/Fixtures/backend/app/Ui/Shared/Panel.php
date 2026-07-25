<?php

namespace Shop\Ui\Shared;

use JesseGall\CodeCommandments\Sins\Backend\NamespaceDependency;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Ui\Tokens\Accent;

/**
 * A panel, assembled from the UI primitives.
 */
#[Righteous(NamespaceDependency::class)]
class Panel
{
    public function __construct(public readonly string $title) {}

    /**
     * The arrow the stack declares: Shared reaches DOWN into the tokens, which is exactly what
     * `mayUse` permits. Nothing down there learns that panels exist.
     */
    public function accent(): Accent
    {
        return new Accent('primary');
    }
}
