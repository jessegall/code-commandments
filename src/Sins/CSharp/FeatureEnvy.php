<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\TellDontAsk;

final class FeatureEnvy extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-feature-envy',
            skill: TellDontAsk::class,
            description: 'a method that loops another object\'s collection or writes its members, reaching into it more than into its own state — behaviour exiled from the object it works on',
            rule: 'Move the behaviour onto the object whose data it works on; ask it (`order.HeaviestLine()`), don\'t reach through it.',
            suggestion: 'Move the method onto the envied type and call it there; keep only the orchestration here.',
        );
    }
}
