<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\RepeatedCallHelper;

final class RepeatedTypeGuard extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-repeated-type-guard',
            skill: RepeatedCallHelper::class,
            description: 'the same chain of type checks — `node is Invocation call && call.Target is MemberAccess` — written at two or more sites, a shape with no name',
            rule: 'Name a shape checked the same way in more than one place once, as a member of the type, and ask for it by name.',
            suggestion: 'Add a method or property that answers the check — `node.IsMemberCall(out var call)` — and use it at every site.',
        );
    }
}
