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
            description: "Handing one subject to a call TWICE over — whole and again flattened (`persist(\$request, \$request->shopId())`), or flattened several ways (`new AgentTurn(\$r->output(), \$r->failed(), \$r->errorOutput())`) — when the callee could derive every piece from the subject itself",
            rule: "Pass the subject, not projections of it — a callee reaching the same value twice should take it once and derive the rest.",
            suggestion: "Give the parameter the subject's type and move the derivations inside the callee; the call site then says what it means instead of spelling out the pieces."
        );
    }
}
