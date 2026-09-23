<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\CSharp;

use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class FixAtTheSource extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'csharp/fix-at-the-source',
            tier: Tier::Mandatory,
            order: 35,
        );
    }

    public function title(): string
    {
        return 'C# fix at the source — fix a value where it is made, not where it breaks';
    }

    public function trigger(): string
    {
        return "Writing a C# constructor that calls a method on something it was handed, a `static` field that methods write to, or a fix for a C# finding that is tempting to patch where it showed up. Read this BEFORE making a constructor do work, keeping state in a static field, or adding a check at a call site, and when a `csharp-constructor-side-effect` or `csharp-mutable-static-state` finding points here.";
    }

    public function intro(): string
    {
        return "A wrong value is almost always wrong where it was made. The call site that fails is only where the
problem showed up; adding a check there leaves the next caller to fail the same way. The same goes for
objects and state in C#: creating an object shouldn't change anything outside it, and state that changes
belongs on an instance someone owns, so every effect happens somewhere you can see.";
    }

    public function summary(): string
    {
        return 'trace a value, an effect or a piece of state back to where it starts, and fix it there.';
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
### Trace it back before you change a line

A finding is a symptom. Before adding an `is null` check, a `?? default` or a `try` where it showed up, ask
where the value came from and follow it back to the code that produced it. Fix that code, and the check
you were about to write — and every copy of it — is no longer needed.

### A constructor sets up; it doesn't act

A constructor says what the object is: it stores what it was given and works out what it needs. When it
calls a collaborator to do something — warm a cache, register itself, open a connection — and ignores
the result, just creating the object changes something outside it, in a line that looks like setup. Keep
the collaborator in a field and call it from the method someone calls, when they choose to.

```csharp
public sealed class Report(Printer printer)
{
    public void Start() => printer.Print("start");
}
```

### State that changes lives on an instance

A `static` field that methods write to is state every caller shares and nobody passes in. Who changed it,
and when, isn't written anywhere. Keep changing state on an instance, pass that instance to the code that
needs it (usually through the constructor), and the dependency is in the signature where a reader sees it.
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            \JesseGall\CodeCommandments\Skills\Backend\FixAtTheSource::class => 'the same discipline in PHP, which every other skill builds on.',
            Absence::class => 'deciding what "missing" means where a value is made is this rule applied to `null`.',
        ];
    }

    public function languages(): array
    {
        return [Language::CSharp];
    }
}
