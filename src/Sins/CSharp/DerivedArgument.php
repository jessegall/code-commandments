<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\PassTheObject;

final class DerivedArgument extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-derived-argument',
            skill: PassTheObject::class,
            description: 'a call that hands over an object and a projection of it — `Persist(request, request.ChannelId)` — or an object in three pieces, where the method could read them itself',
            rule: 'Pass the object once and let the method read what it needs from it; a value the method can derive from an argument it already gets is one it should derive itself.',
            suggestion: 'Drop the projected parameter and read it inside (`Persist(request)` reading `request.ChannelId`), or take the object in place of its pieces.',
        );
    }
}
