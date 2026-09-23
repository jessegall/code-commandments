<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\TellDontAsk;

final class KeyedLookupEnvy extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-keyed-lookup-envy',
            skill: TellDontAsk::class,
            description: 'a method that uses an object\'s key to fetch a fact about it through a collaborator — `self.registry.get(node.key).reserved` — treating the object as a key into its own data',
            rule: 'Put the fact on the object it is about and ask it (`node.reserved_names()`), instead of looking it up from outside by the object\'s key.',
            suggestion: 'Give the object the method, holding what it needs to answer, and call it where this method was called.',
        );
    }
}
