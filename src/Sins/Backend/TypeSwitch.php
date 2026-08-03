<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\TellDontAsk;

final class TypeSwitch extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'type-switch',
            skill: TellDontAsk::class,
            description: "two or more `instanceof` tests on the same subject deciding different branches — asking a value what it IS instead of telling it what to do",
            rule: "Let each type answer for itself; never branch on what a value IS when a method on it could say what it DOES.",
            suggestion: "A method on the shared interface, implemented per type — so a new type needs no edit here at all.",
        );
    }
}
