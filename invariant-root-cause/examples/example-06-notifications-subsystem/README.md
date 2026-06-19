# Example 06 — a multi-file subsystem (everything together, no ambiguity)

A realistic little **notifications** subsystem (≈6 files) that combines four invariant smells across
files, plus one *correct* `Option<T>` use kept untouched. This is the "see it in a codebase, not a
snippet" example.

```
initial/
  Severity.php              enum: escalationMinutes() has `default => null`
  Channel.php               interface
  ChannelRegistry.php       find(): ?Channel  (?? null, callers de-null)
  Template.php              value object
  TemplateStore.php         lookup(): Option<Template>  (GENUINE — fine) ...
  AlertDispatcher.php       ... but consumed with ->getOr(null); + private ?Channel helper
```

## Findings, per file, with order (root cause leads symptom)

1. **`Severity::escalationMinutes()`** — `match` over a closed-set enum with `default => null`, `Critical`
   forgotten.
   - ROOT: **`ThrowOnUnhandledCaseProphet`** → SYMPTOM: `PreferOptionOverNullProphet` (deferred/annotated).
   - Downstream: `AlertDispatcher` does `escalationMinutes() ?? 0` (dead after the fix).

2. **`ChannelRegistry::find()`** — `?Channel` via `?? null`; `AlertDispatcher::channelFor()` de-nulls it.
   - ROOT: **`RegistryReturnContractProphet`** → SYMPTOMS: `NoNullCoalesceToNullProphet` *(auto-fixable —
     must be skipped by `repent` while the root cause is open)* and `PreferOptionOverNullProphet`.

3. **`AlertDispatcher::channelFor()`** — **private** `?Channel` helper whose every caller de-nulls.
   - ROOT: **`PreferTotalOverNullableProphet`** → SYMPTOM: `PreferNullObjectDefaultsProphet` /
     `PreferOptionOverNullProphet`.

4. **`AlertDispatcher::renderBody()`** — `$this->templates->lookup($key)->getOr(null)?->render(...) ?? ''`.
   The template for a *known severity* must exist — this is an invariant, but the `Option` is collapsed
   back to `null`.
   - ROOT: **`NoOptionToNullProphet`** (`getOr(null)`) → SYMPTOM: the trailing `?? ''` laundering.
   - **Important `Option<T>` contrast:** `TemplateStore::lookup()` returning `Option<Template>` is **kept
     as-is** — a *user-customised* template legitimately may not exist (genuine absence). The fix is not
     "stop using Option," it is "don't use the *optional* API for a *required* template." `final/` adds a
     separate `require()` that throws, and leaves `lookup()` (the real Option) alone.

> Cross-file note: each finding's root cause and symptom sit in the **same file/region**, so `supersedes`
> deferral applies per file; under `--prophet=` filtering the symptom-side `rootCauses()` trigger still
> surfaces the hint. No finding's correct fix depends on a *different* file being fixed first.

## The fix (`final/`)

- `Severity` → total `match`, returns `int` (**remove** `default`).
- `ChannelRegistry` → `get(): Channel` throws + `has()`; **ADD** `ChannelNotRegisteredException`.
- `AlertDispatcher::channelFor()` → total `Channel` (via `registry->get()`).
- `AlertDispatcher::renderBody()` → `templates->require($key)->render(...)`; **ADD**
  `TemplateNotFoundException`; the smelly `getOr(null) … ?? ''` is gone.
- `TemplateStore::lookup(): Option<Template>` → **unchanged** (genuine absence, Option is correct); a new
  `require(): Template` is added for the invariant path.

**Class changes:** **ADD** `ChannelNotRegisteredException`, `TemplateNotFoundException`. No class removed
here (contrast example 05, which removes a redundant wrapper).

> `Option` here = the project's Option monad (`fromValue` / `some` / `none` / `map` / `getOr` /
> `getOrThrow`), shown illustratively.
