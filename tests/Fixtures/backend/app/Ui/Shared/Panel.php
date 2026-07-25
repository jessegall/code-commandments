<?php

namespace Shop\Ui\Shared;

use JesseGall\CodeCommandments\Sins\Backend\NamespaceDependency;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Ui\Elements\Badge;

/**
 * A panel, assembled from the UI primitives.
 */
#[Righteous(NamespaceDependency::class)]
class Panel
{
    public function __construct(public readonly string $title) {}

    /**
     * The arrow the stack declares: Shared reaches DOWN into Elements, which is exactly what
     * `mayUse: ['Shop\Ui\Elements']` permits. Nothing in Elements learns that panels exist.
     */
    public function heading(): Badge
    {
        return new Badge(strtoupper($this->title));
    }
}
