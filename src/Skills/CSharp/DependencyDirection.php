<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\CSharp;

use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class DependencyDirection extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'csharp/dependency-direction',
            tier: Tier::KeepInMind,
            order: 44,
        );
    }

    public function title(): string
    {
        return 'C# dependency direction — references point down the stack';
    }

    public function trigger(): string
    {
        return "Using a type from another of the project's own namespaces. Read this before `Shop.Domain` code reaches for a type in `Shop.Web`, before a new `using` between two of your namespaces, and when a namespace-cycle or namespace-dependency finding points here. A declared layer may only use the layers it said it may use.";
    }

    public function intro(): string
    {
        return "A namespace is a claim about what depends on what. One reference going the wrong way makes the two
        namespaces really just one namespace split across two names, so the direction is declared once and every
        reference is checked against it.";
    }

    public function summary(): string
    {
        return 'a declared layer may only use the layers it declared it may use — down the stack, never back up, never sideways, and never in a cycle.';
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
`Shop.Domain` says "this is the business, and it knows nothing about how it is shown"; `Shop.Web` says "this
is built on top of the domain". The claim is worth exactly as much as the code: one `OrderPage` used inside
`Shop.Domain` and the two can no longer be understood, tested or reused apart.

Nothing about the arrow shows when you write it. The project compiles and the tests pass. When both
namespaces live in one project, the compiler never stops a reference going the wrong way.

### Declare the stack

The direction is declared once, in the project's `.commandments/config.php`, each layer naming the layers
it may use:

```php
$config->configure(fn (\JesseGall\CodeCommandments\Detectors\CSharp\NamespaceDependencyDetector $d) => $d
    ->layer('Shop.Domain')                                         // the business: itself only
    ->layer('Shop.Application', mayUse: ['Shop.Domain'])           // use cases over the domain
    ->layer('Shop.Web', mayUse: ['Shop.Application', 'Shop.Domain'])
);
```

### What is judged

- **Every reference the compiler resolved**, not only the `using` lines: a type in a declaration, the type
  of a value, the target of a call, and the type arguments inside any of them. A fully qualified name counts
  the same as one brought in by a `using`.
- **Only the project's own types.** A framework or package type is always allowed.
- **Only from a declared layer.** A layer contains its own nested namespaces, so references within a layer
  are fine.
- **Cycles, declared or not.** Two of the project's namespaces that each use the other are one namespace,
  whatever the folders say.

### First: which side is wrong?

A layer finding measures the code against a declaration someone wrote, and a declaration can be stale. Before
changing either, ask whether the declared stack still describes the design this project wants. Usually the
reference is the accident; when you conclude the declaration is wrong, say so to the user with your
reasoning. Never quietly edit the declaration to make a finding go away.

### The fix

Move the thing both sides need down into the lower layer, pass it in from above, or invert the dependency
behind an interface the lower layer owns and the upper layer implements.
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            \JesseGall\CodeCommandments\Skills\Backend\DependencyDirection::class => 'the same discipline over PHP namespaces.',
            FixAtTheSource::class => 'the arrow that points the wrong way is where the fix belongs.',
        ];
    }

    public function languages(): array
    {
        return [Language::CSharp];
    }
}
