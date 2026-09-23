<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Frontend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Frontend\VueComponents;

final class NearDuplicateElement extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'near-duplicate-element',
            skill: VueComponents::class,
            description: "Markup with one skeleton repeated 2+ times — the same tags, attributes and nesting binding different data — within a template, across components, or as two components' whole templates",
            rule: "Extract markup that repeats with different data into one component, and pass what differs as props.",
            suggestion: "Make the shared skeleton a component; each place that repeated it renders the component with its own data.",
        );
    }
}
