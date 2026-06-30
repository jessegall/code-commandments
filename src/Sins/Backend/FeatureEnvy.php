<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\TellDontAsk;

final class FeatureEnvy extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'feature-envy',
            skill: TellDontAsk::class,
            description: "Exiled behaviour / feature envy — a method operating on ONE other owned object's internals that belongs ON that object",
            rule: "Behaviour belongs with its data — move a method that loops or queries one other owned object onto that object.",
            suggestion: "Move the method onto the object (`\$node->edges()`)."
        );
    }
}
