<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Backend;

use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class DependencyDirection extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'backend/dependency-direction',
            tier: Tier::KeepInMind,
            order: 17,
        );
    }

    public function title(): string
    {
        return "Dependency direction — a layer may only reach DOWN";
    }

    public function trigger(): string
    {
        return "Which layer may know about which. When a project declares its layers (`\$config->configure(fn (NamespaceDependencyDetector \$d) => \$d->layer('App\\\\Ui\\\\Elements')->layer('App\\\\Ui\\\\Shared', mayUse: ['App\\\\Ui\\\\Elements']))`), every reference OUT of a declared layer must point at a layer it is allowed to use — down the stack, never back up and never sideways. Read this before adding an import, a type hint, a `new`, or a static call that crosses a namespace boundary, before moving a class between namespaces, and when deciding where a new class belongs.";
    }

    public function intro(): string
    {
        return "Layers are only real if the arrows all point one way. The moment a low-level
namespace reaches back up at the thing that uses it, the layer stops being a
layer and becomes a knot you cannot pull apart.";
    }

    public function summary(): string
    {
        return "a declared layer may only reference the layers it declared it may use — down the stack, never back up, never sideways; the direction is enforced from the project's own layer declaration.";
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
A namespace is a claim about **what depends on what**. `App\Ui\Elements` says "these
are the primitives"; `App\Ui\Shared` says "these are built FROM the primitives". The
claim is worth exactly as much as its arrows: one `use App\Ui\Shared\…` inside
`Elements` and the two namespaces are a single tangled unit wearing two names.

Nothing about the arrow is visible at the moment you write it. An import is one line,
it compiles, the tests pass. The cost lands later — you cannot extract, reuse, test in
isolation, or reason about the low layer without dragging the high one along, and by
then the cycle has a dozen strands.

So the direction is **declared**, once, in the project's `.commandments/config.php` —
each layer names the layers it may use — and every reference out of a declared layer
is judged against it:

```php
$config->configure(fn (NamespaceDependencyDetector $d) => $d
    ->layer('App\\Ui\\Elements')                                // primitives: itself only
    ->layer('App\\Ui\\Shared', mayUse: ['App\\Ui\\Elements'])   // composed FROM primitives
    ->layer('App\\Domain')                                      // knows nothing about UI
);
```

Read it as the stack, top-down: a layer may reach the layers it listed, and nothing
else that is declared.

### What the rule judges

- **Every reference, not just imports** — `extends`, `implements`, a trait `use`, a
  parameter/return/property type, `new X`, `X::method()`, `X::CONST`, `instanceof`, a
  `catch` type, an attribute. An alias or a fully-qualified name in the middle of a
  method is the same arrow as an import at the top.
- **Only DECLARED namespaces.** A framework, a vendor package, or any namespace the
  project never declared is always allowed — the rule constrains the layering you
  chose, it does not invent one.
- **Only FROM a declared layer.** Code outside every declared layer is unconstrained:
  it has not opted in. A leaf layer everyone uses stays usable by everyone.
- **A layer contains its own sub-namespaces.** `App\Ui\Elements\Button` is inside
  `App\Ui\Elements`, so references within a layer are always fine.

### First: which side is wrong?

A layer finding is the ONE finding in this package that is not decidable from the code
alone. Every other rule reads the AST and knows; this one measures the code against a
DECLARATION a human wrote (or `commandments layers` inferred from whatever the code
happened to do that day). The declaration is a claim about the intended design, and a
claim can be stale, half-migrated, or simply wrong.

So read the finding as *these two are in tension*, and decide which side is at fault
before you change either. Ask: does the declared stack still describe the architecture
this project wants? Would someone who knows this codebase call this arrow a mistake, or
call the layer boundary the mistake? The answer is usually "the code" — a layer breach
is normally an accident of convenience — but you have to actually ask, because obeying a
wrong declaration means refactoring good code into a shape nobody intended.

The verdict is yours to reach and the user's to confirm. If you conclude the declaration
is wrong, SAY SO to the user in as many words, with the reasoning — never fix it silently.

### Fixing one

Do NOT widen `mayUse` to make the finding go away — that edits the architecture to
match the accident, and it is the one move that turns a red run green while making the
design worse. In order of preference:

1. **Invert the arrow.** The low layer usually needs a *value*, not the high layer's
   class: take the primitive, an enum, a value object, a callback. The caller in the
   high layer supplies it.
2. **Move the class.** If two layers both need it, it is a primitive that belongs in
   the lower one (or in a new shared layer beneath both).
3. **Introduce a contract the low layer owns.** The low layer declares an interface;
   the high layer implements it. The arrow now points down, and the name says so.
4. **Change the declaration** — when the judgment above says the stack itself is wrong.
   That is a real option, not a defeat: state the decision and its reasoning to the user,
   change the layer that is misdeclared (not just the one `mayUse` entry the finding
   named), and leave the codebase saying what it means. What is forbidden is reaching for
   this because 1–3 looked like work.

### The tell

You are typing a `use` and the namespace you are importing is *above* the file you
are typing it in. Stop and ask what value you actually need from up there — it is
almost always something smaller than the class you were about to reach for.
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            ValueObjects::class => "the value a low layer needs instead of the high-level class it reached for.",
            TellDontAsk::class => "reaching up for an object's data is the same instinct, one scale down.",
        ];
    }
}
