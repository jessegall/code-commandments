<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\TellDontAsk;

final class FeatureEnvy extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-feature-envy',
            skill: TellDontAsk::class,
            description: 'a method that loops another object\'s collection or writes its fields, reaching into it more than into its own state — behaviour exiled from the object it works on',
            rule: 'Move the behaviour onto the object whose data it works on; ask it (`order.heaviest_line()`), don\'t reach through it.',
            suggestion: 'Move the method onto the envied class and call it there; keep only the orchestration here.',
        );
    }
}
