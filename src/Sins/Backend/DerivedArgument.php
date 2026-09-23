<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\PassTheObject;

final class DerivedArgument extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'derived-argument',
            skill: PassTheObject::class,
            description: 'Passing the same object twice — once whole and once broken into a piece (`persist($request, $request->shopId())`), or broken into several pieces at once (`new AgentTurn($r->output(), $r->failed(), $r->errorOutput())`) — when the callee could derive each piece itself from the one object.',
            rule: 'Pass the object itself, not values derived from it — if a callee needs several pieces off one object, give it the object once and let it work out the rest.',
            suggestion: "Give the parameter the subject's type and move the derivations inside the callee; the call site then says what it means instead of spelling out the pieces."
        );
    }
}
