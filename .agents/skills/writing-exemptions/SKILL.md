---
name: writing-exemptions
description: How a general rule stays framework-agnostic yet avoids false positives on framework types — a package states its facts once as data (rules/packages/<ecosystem>/<package>.json): the effects of its calls and the exemptions it registers under a tag; a rule asks for a tag with exemptedBy and never names a framework. Read this when a general rule must not fire on a framework's boundary/contract/config type, or when adding a package's facts.
---

# Writing exemptions — keep a general rule general

A *general* structural rule (feature-envy, array-bag, near-duplicate…) sometimes must know a fact about a
**framework**: that a class is a request boundary, that a method's signature is dictated, that a call runs a
query. But a general rule may **not** name a framework. A package file states the fact once, as data, and the
rule asks for it by tag or effect.

## The two pieces

1. **The package file** — `rules/packages/<ecosystem>/<package>.json` (`composer/laravel-framework.json`,
   `pypi/django.json`, `nuget/microsoft-entityframeworkcore.json`; a language's own library under
   `rules/packages/standard/`). It names the package, its ecosystem and versions, and holds:

   ```json
   "entries": [
     { "type": "Illuminate\\Database\\Eloquent\\Builder", "name": "get", "effect": "executes-query", "execution": "call" }
   ],
   "exemptions": [
     { "tag": "contract-method", "on": { "Illuminate\\Foundation\\Http\\FormRequest": ["rules"] },
       "why": "A framework-mandated method whose signature the framework dictates." }
   ]
   ```

   An entry gives a type's member an **effect** (`executes-query`, `mutates-receiver`, `blocks-thread`, …). An
   exemption registers a **tag** (`boundary`, `contract-method`, `no-container`, `composition-root`,
   `dynamic-members`, …) on whole `classes`, on methods of a class (`on`), on a method name anywhere
   (`methods`) or on an `attributes`. Every exemption says `why`.

2. **The rule asks** for the tag or effect over the tree, by the node's resolved symbol:

   ```json
   ["not", ["exemptedBy", "contract-method", "$m"]]
   ```

   A package applies only when the judged project holds it (its manifest or lock file names it), so a rule
   never excuses a type the project does not use.

## Rules

- **A general rule NEVER names a framework type.** It reads a tag or an effect; a package file supplies the
  types. A package's own rules (`rules/php-spatie-data/`, `rules/php-laravel-idioms/`) may name its types.
- **A package's facts live once**, in its package file. Shared checks over them go into
  `rules/definitions/<package>.json`, never restated in each rule.
- **Say why.** The `why` is what `commandments exemptions` shows, so the reason cannot drift from what quiets
  the rule.

## Verify

- `commandments exemptions` lists every tag with the package that owns it.
- `commandments exemptions <sin>` shows the tags one rule honours.
- A new tag or entry is proven by a fixture marker the rule must leave alone (`@righteous`), in the fixture
  project of that language.

## Related

- [[writing-detectors]] · [[detector-engine]]
