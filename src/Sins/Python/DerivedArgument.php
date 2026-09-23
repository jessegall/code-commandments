<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\PassTheObject;

final class DerivedArgument extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-derived-argument',
            skill: PassTheObject::class,
            description: 'a call that hands over an object and a projection of it — `persist(request, request.channel_id)` — or an object in three pieces, where the function could read them itself',
            rule: 'Pass the object once and let the function read what it needs from it; if a value can be derived from an argument already passed in, the function should derive it itself.',
            suggestion: 'Drop the projected parameter and read it inside (`persist(request)` reading `request.channel_id`), or take the object in place of its pieces.',
        );
    }
}
