---
name: writing-exemptions
description: How a general detector stays framework-agnostic yet avoids false positives on framework types — the open exemption registry. A Package registers exemptions keyed by a tag (an Exemption subclass with a slug + description); a detector reads the tag via Exemptions::has and declares it via Exemptable. Read this when a general rule must not fire on a framework's boundary/contract/config type, or when adding a package's exemptions.
---

# Writing exemptions — keep a general rule general

A *general* structural rule (feature-envy, array-bag, near-duplicate…) sometimes must
know a fact about a **framework** — that a class is a request boundary, that a method's
array shape is contractual, that a type is instantiated without a container — so it
doesn't false-positive on it. But a general detector may **not** name a framework. The
exemption registry is how the fact reaches the rule without either side importing the
other.

## The three pieces

1. **A tag** — always an `Exemption` subclass (`src/Packages/Exemption.php`), with a
   `slug()` and a `description()`. The built-ins live in `src/Packages/Tags/`
   (`Boundary`, `ContractMethod`, `ArrayReturning`, `NoContainer`). A custom tag is your
   OWN subclass — never a random class; `Exemption::resolve()` enforces that.

2. **A `Package`** (`src/Packages/*Package.php`, auto-enrolled) registers exemptions in
   `register()`, building each tag's clause fluently:

   ```php
   $exemptions->exempt(Boundary::class)->classes(...LaravelNode::REQUEST_TYPES);
   $exemptions->exempt(ContractMethod::class)->on(LaravelNode::FORM_REQUEST, 'rules');
   ```
   `classes(...)` = whole classes (any method), `on(class, ...methods)` = specific
   methods, `methods(...)` = a method name anywhere. FQCNs come from the package's
   decorator node (`LaravelNode::*`) — stated ONCE, never re-declared in the package.

3. **The detector reads the tag** and declares it via `Exemptable`:
   ```php
   final class FeatureEnvyDetector implements Detector, Exemptable
   {
       public function exemptions(): array { return [Boundary::class]; }
       // inside find():
       ->reject(fn (AstNode $n) => Exemptions::has(Boundary::class, $codebase, $n->enclosingClassName()))
   }
   ```
   `exempt('boundary')` (the slug) is the same as `exempt(Boundary::class)` — package
   devs need only the slug of a well-known tag.

## Rules

- **A general detector NEVER names a framework FQCN.** It reads a tag; a package supplies
  the types. If it needs a framework concept only as an exemption, that's the registry.
- **A package's FQCNs live once — on its decorator node** (`Ast\{Laravel,Spatie,…}\*Node`),
  not re-declared in the `Package`. Pull `LaravelNode::FORM_REQUEST`, don't restate the literal.
- **Declare what you read.** `implements Exemptable` so `commandments exemptions <detector>`
  can show what quiets it, and the declaration can't drift from the `has()` calls.
- **Every tag is describable + sluggable.** A custom exemption is an `Exemption` subclass
  with `slug()` + `description()`; it then lists in `commandments exemptions` like a built-in.

## Verify

- `commandments exemptions` lists every tag (built-in + detector-declared) with slug + description.
- `commandments exemptions <sin|detector>` shows the tags one detector honours.
- The exemption clause matching is unit-tested in `tests/Packages/ExemptionsTest.php`; a new
  built-in tag or package registration is proven there.

## Related

- [[writing-detectors]] · [[detector-engine]]
