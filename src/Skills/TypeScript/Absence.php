<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\TypeScript;

use JesseGall\CodeCommandments\Language;

use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class Absence extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'typescript/absence',
            tier: Tier::KeepInMind,
            order: 26,
        );
    }

    public function title(): string
    {
        return 'TypeScript absence — say what is missing, and mean it';
    }

    public function trigger(): string
    {
        return "Modelling a value that might not be there in TypeScript — a `?? default`, an `?.` chain, a `field?: T`, a `=== null` or `=== undefined` test. Read this BEFORE writing any of them in a .ts module or a component's script. TypeScript has TWO ways to be missing where PHP has one, and a type that admits absence the design never has is a lie the compiler will not catch.";
    }

    public function intro(): string
    {
        return "TypeScript can say a value is missing in two ways, and the difference matters.
`null` is an absence someone WROTE; `undefined` is an absence that simply happened —
a property never set, an argument never passed. A codebase that treats them as
interchangeable has no idea which it is looking at, and the `?? default` written to
cope with either is the moment a real absence stops being handled and starts being
hidden.";
    }

    public function summary(): string
    {
        return 'model absence honestly — one spelling for missing, no `??` that invents a value, no `?.` on something always set.';
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
### The type is the claim; the defence is the tell

`field?: T` and `T | null` are claims that the value can be missing. If every path
writes it before anything reads it, the claim is false — and the `?.` and `??` scattered
downstream exist only to satisfy a compiler about a case the program never has. Delete
the optionality and the defences go with it.

The reverse is the same rule read backwards: where a value REALLY can be missing, the
absence is a case to handle, not a hole to fill. `?? 0`, `?? ''`, `?? []` answer the
compiler and lose the question — was it missing, or was it genuinely zero?

### `null` and `undefined` are not two names for one thing

Pick one to MEAN "missing" and let the other be a bug. A property that is sometimes
`null` and sometimes `undefined` forces every reader to test for both, and a test for
one is a silent hole for the other. The two are distinguishable at the type level, so
a codebase that uses both interchangeably has thrown that away for nothing.

### What TypeScript does NOT get from the backend

There is no `Option` here, and no Null Object worth the ceremony for a plain data
shape. The tools are the type itself (`T` vs `T | null`), a narrowing guard at the top
of the function, and a total value the caller can always use. That is the whole kit —
which is why this is its own skill rather than a translation of the PHP one.
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            \JesseGall\CodeCommandments\Skills\Backend\Absence::class => 'the same instinct on the server, with the tools PHP has and TypeScript does not.',
            \JesseGall\CodeCommandments\Skills\Backend\TypeHonesty::class => "the general rule this serves: a type must not claim an optionality the design doesn't have.",
        ];
    }

    /**
     * The discipline is the LANGUAGE's — two spellings for missing, which PHP does not have.
     */
    public function languages(): array
    {
        return [Language::TypeScript];
    }
}
