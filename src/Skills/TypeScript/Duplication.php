<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\TypeScript;

use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class Duplication extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'typescript/duplication',
            tier: Tier::KeepInMind,
            order: 27,
        );
    }

    public function title(): string
    {
        return 'TypeScript duplication — one behaviour, one home';
    }

    public function trigger(): string
    {
        return "Copying a function body from one component or module into another — the same `load`/`format`/`submit` written a second time in a different `<script setup>` or `.ts` file, or a near-copy that differs only in the endpoint, the field or the label it uses. Read this BEFORE pasting a function you already wrote somewhere else, and when a `duplicate-function` finding points here. The fix is a shared function or composable that both call, parameterised by whatever actually differs.";
    }

    public function intro(): string
    {
        return "A function written twice is one decision living in two places. The day it has to
change, one copy gets the fix and the other keeps the bug — and nothing in either file
says the other exists. In a Vue codebase this happens one component at a time: each
`<script setup>` grows its own `formatPrice`, its own `loadPage`, until the behaviour
the app depends on is spread across files that do not know about each other.";
    }

    public function summary(): string
    {
        return 'a function body written twice becomes one shared function or composable, parameterised by what differs.';
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
### A copy is a decision made twice

Two functions with the same body are the same code whatever they are called — and the
case worth catching is exactly when they are NOT called the same, because then nobody
searching for one finds the other. Hoist the body to ONE home and let every caller use it:

- a plain function in a shared module (`utils/money.ts`) when it only computes;
- a composable (`useOrders()`) when it holds reactive state or lifecycle;
- a method on the class that owns the data, when it reads one object's fields.

### A near-copy is a missing parameter

Two bodies with the same control flow that differ only in a literal — an endpoint, a
field name, a label — are one function waiting for an argument. Name what differs and
pass it; do not keep two copies because the difference "is only a string". The string
is the parameter.

### What is NOT duplication

Short bodies are alike by coincidence: a one-line delegate, a getter, a `return x.y`
cannot be hoisted into anything smaller than itself. Neither can two constructors of
two different classes. Duplication is a body of real substance, twice.
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            \JesseGall\CodeCommandments\Skills\Backend\FixAtTheSource::class => 'the same instinct on the server — one decision, made once, where it is born.',
            \JesseGall\CodeCommandments\Skills\Frontend\VueComponents::class => 'the template twin: markup written twice is a component waiting to be extracted.',
        ];
    }

    /**
     * A duplicated function is written in a `.ts` module or a component's `<script>` alike.
     */
    public function languages(): array
    {
        return [Language::TypeScript, Language::Vue];
    }
}
