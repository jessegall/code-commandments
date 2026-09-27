---
name: writing-exemptions
description: How a general detector stays framework-agnostic yet avoids false positives on framework types — the open exemption registry (engine/php/packages). A Package registers exemptions keyed by a Tag (a slug and a description); a detector declares the tags it honours and where to match them (Exemptions()) and packages.Exempt drops what they excuse. Read this when a general rule must not fire on a framework's boundary/contract/config type, or when adding a package's exemptions.
---

# Writing exemptions — keep a general rule general

A *general* structural rule (feature-envy, array-bag, near-duplicate…) sometimes must know a fact about a
**framework** — that a class is a request boundary, that a method's array shape is contractual, that a type is
instantiated without a container — so it doesn't false-positive on it. But a general detector may **not** name
a framework. The exemption registry (`engine/php/packages`) is how the fact reaches the rule without either
side importing the other.

## The three pieces

1. **A tag** — a `packages.Tag`, a slug and a description. The shipped ones are in `tags.go` (`Boundary`,
   `ContractMethod`, `ArrayReturning`, `NoContainer`, `Association`, `CompositionRoot`, `ControlSignal`) and
   listed in `packages.Tags`.

2. **A `Package`** registers exemptions in `Register`, building each tag's clause fluently, and is listed in
   `packages.Shipped`:

   ```go
   exemptions.Exempt(Boundary).Classes(laravel.RequestTypes...)
   exemptions.Exempt(ContractMethod).On(laravel.FormRequest, "rules")
   ```

   `Classes(...)` = whole classes (any method), `On(class, methods...)` = specific methods, `Methods(...)` = a
   method name anywhere, `Attributes(...)` = an attribute. The types come from the package's own engine package
   (`engine/php/laravel`, `engine/php/spatie`) — stated ONCE, never re-declared in the `Package`.

3. **The detector DECLARES the tags it honours and WHERE to match them**, and lets `packages.Exempt` drop the
   excused findings centrally:

   ```go
   // Exemptions excuses a class whose job is handing the framework arrays, and a method whose signature it dictates.
   func (ArrayReturnBagDetector) Exemptions() []packages.Exemption {
   	return []packages.Exemption{
   		{Tag: packages.ArrayReturning, By: []packages.By{packages.EnclosingClass}},
   		{Tag: packages.ContractMethod, By: []packages.By{packages.EnclosingMethod}},
   	}
   }

   func (d ArrayReturnBagDetector) Find(codebase *engine.Codebase) []engine.Match {
   	return packages.Exempt(codebase, d, codebase.Where(…).Get())
   }
   ```

   `EnclosingClass` matches the finding's class, `EnclosingMethod` its class and method. A tag declared with no
   `By` is one the detector asks itself (a bespoke subject — e.g. a parameter's resolved type, through
   `packages.Excuses`), declared only so `exemptions` lists it.

## Rules

- **A general detector NEVER names a framework type.** It reads a tag; a package supplies the types. If it needs
  a framework concept only as an exemption, that's the registry.
- **A package's types live once — in its own engine package**, not re-declared in the `Package`. Use
  `laravel.FormRequest`, don't restate the literal.
- **Declare what you read.** `Exemptions()` is what `commandments exemptions <detector>` shows, so the
  declaration can't drift from what quiets the rule.

## Verify

- `commandments exemptions` lists every tag with its slug and description.
- `commandments exemptions <sin|detector>` shows the tags one detector honours.
- Clause matching is unit-tested in `engine/php/packages`; a new tag or package registration is proven there.

## Related

- [[writing-detectors]] · [[detector-engine]]
