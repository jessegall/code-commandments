<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\FixAtTheSource;

final class MutableStaticState extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'mutable-static-state',
            skill: FixAtTheSource::class,
            description: 'A write to a static property — really a global variable with a namespace attached — where whichever write happens last wins, so the order code runs in changes the result.',
            rule: "Hold changing state on an INSTANCE someone owns and passes; never write a static property.",
            suggestion: "Constructor-inject the state as a collaborator, so who holds it (and who may change it) is written down.",
        );
    }
}
