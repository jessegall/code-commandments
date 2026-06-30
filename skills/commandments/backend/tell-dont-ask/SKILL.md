---
name: tell-dont-ask
description: Behaviour belongs with the data it operates on (feature envy, Fowler). If a method reaches through ONE other object's internal structure — looping its collection, walking its tree of parts — to work out something the object should answer itself, that logic is exiled from its home; Move the Method onto the object (`$node->edges()`, not `EdgeDetector::detect($node)`). Read this BEFORE you write a `*Detector`/`*Walker`/`*Finder` that iterates one object's collection from the outside. NOTE the exception: a policy/Strategy over the object's flat scalar fields (a grade, a label, a classification) is NOT envy.
---

# Tell, don't ask — behaviour belongs with its data

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> An object that holds the data to answer a question should answer it. When the answer is computed
> somewhere else — a separate class reaching in to read its fields and derive a result — the behaviour
> has been **exiled** from its home. Move it back: `$node->edges()`, not `EdgeDetector::detect($node)`.

## The principle

### The sin: exiled behaviour (feature envy)

This is the classic **feature envy** smell (Fowler): *a method more interested in another object's data than
its own.* It **reaches through one other object's internal structure — iterating its collection, walking its
tree of parts** — to work out something the object should answer itself, while accessing that object more
than its own (`$this`) state. The fix is Fowler's **Move Method**: the loop belongs on the object whose
collection it walks, so `$node->edges()` replaces `EdgeDetector::detect($node)`.

**Structure, not surface.** The tell is *operating on the object's internals* — four shapes:

1. **Iterate** its collection — `foreach ($node->outputs …)`, a recursive walk of `$block->left`/`->right`.
2. **Query** its collection from outside — `array_reduce($order->lines(), …)`,
   `in_array($branch, $ctx->descriptor->handleNames())`. You exported its collection to run the
   aggregate/membership/search out here; the answer belongs on the object (`$order->total()`,
   `$descriptor->hasBranch($branch)`). This holds **however deep the object is nested** — follow the chain
   to the object that owns the data.
3. **Mutate** its state — `$account->frozen = true; $account->strikes++` from another class. Reaching in to
   set its fields is read-then-mutate envy (the canonical `clone $date; $date->modify(…)`); the transition
   belongs on the object (`$account->freeze()`). *(At a Laravel model + `save()`, this is also the
   [`laravel-idioms`](../laravel-idioms/SKILL.md) model-mutation sin — both are real; fix once, on the model.)*
4. **Key into it** — use the object's *identity* to look up a fact about it through a collaborator:
   `$this->registry->get($node->key)->reservedOutputNames`, `$this->descriptors->descriptorFor($node->key)->isBodyHandle($port)`.
   The object is being treated as a key into its own data; the answer belongs ON it
   (`$node->reservedOutputNames()`, `$node->isControlHandle($port)`). This is the *indirect* form — the
   data isn't on the object, but it's reachable from the object's identity, so the method should be too.
   It holds however many collaborators sit in between.

   **Home it on the data, not the store.** The fix is to move the behaviour onto the object the lookup
   *resolves to* — the descriptor / value the key returns (`$descriptor->reservesOutput($name)`), or, better,
   the object you already hold whose identity you keyed with (`$node->reservedOutputNames()` — the node
   delegates to its descriptor *once*, in one place). Do **NOT** push a `reservedOutputNamesFor($key)` query
   onto the **registry / store**. A `*Registry` is a *keyed store* — its job is `get`/`has`; adding a domain
   query there just relocates the same `has()?get()` dance inside the store and leaves every caller still
   *asking* with a raw key instead of *telling* the object. Ask: *which object already has the answer (or can
   get it in one hop)?* — that is the target, never the lookup table you went through.

A method that only reads the object's **flat scalar fields** to compute a value — a grade, a label, a
yes/no — is an **external policy**, not envy. That's a *Strategy*, the documented exception: scoring,
formatting, and classifying legitimately live in their own class, because the rule can vary independently of
the data. Reading fields is fine; *operating on the object's internals* is the sin.

**Why it's a sin:**

- The knowledge is **split from the data it's about** — they drift apart and must be hand-synced.
- Every caller must route through the external helper and hand it the object, instead of just **asking
  the object**.
- The object goes **anemic** — it carries the data, but the behaviour that defines it lives elsewhere.

### The fix

Move the computation onto the object whose data it consumes. The external method delegates to it
(`return $node->edges();`) or disappears. If the external class has nothing left, delete it.

**Pick the right home — the data owner, not a pass-through.** The target is the object that actually *owns*
the data (the domain entity, or the value a key resolves to), and ideally the object **you already have in
hand**. Two traps that aren't fixes, just relocations:

- **Pushing it onto the keyed store / registry** you looked through (`Registry::reservedOutputNamesFor($key)`).
  A store's contract is `get`/`has`; a domain query bolted on still leaves callers handing it a raw key
  instead of asking the object. Move it onto the thing the key *identifies* instead.
- **Adding a thin forwarder** on a collaborator that just re-exposes another object's data. If the object you
  were given (`$node`) can answer it — directly or by delegating to its own descriptor once — that is the home.

### What is NOT this sin

The boundaries matter — over-applying this turns every collaborator call into a false alarm.

- **Calling getters / fetchers / methods on another object to do _your own_ work** is fine. A single ask,
  or asks woven into logic that genuinely belongs to *this* class, is not envy.
- **Orchestrating several collaborators** into a result — a service or assembler combining *many* objects
  — is correct layering. It is not envious of any *one* of them; moving it onto any single collaborator
  would couple that one to the rest.
- **A from-source factory / mapper** that turns object B into a **different** type (a DTO, a presentation
  or persistence shape) is fine. Moving it onto B would invert the dependency — B must not know about its
  own DTO. A static method that returns its own type (`SomeData::forNode(WorkflowNode $n): self`) is this
  pattern, not envy.
- **A method that uses its own class's state** (config, other fields, injected collaborators) is not a
  hollow shell — it has a reason to live where it is.
- **A policy / calculation over flat scalar fields — a Strategy.** A `*Grader`/`*Policy`/`*Classifier` that
  reads the object's scalar fields and yields a grade, decision, or label (`$dims->grams > 30000 || …`) is
  the documented Strategy exception: it operates on the surface, not the structure, and the rule varies
  independently of the data. Leave it.
- **Pure formatting / presentation** — assembling the fields into a display value
  (`sprintf('%dg %d×%d', $d->grams, $d->w, $d->h)`). It renders the object; it doesn't walk it.

### The tell

The smell is a **loop over another object's collection** (or a recursive walk of its parts) inside a class
that isn't that object — `foreach ($line->children …)`, `containsAggregate($block->left)`. It's working the
object's internals from the outside. Ask: *does the object I'm walking already hold everything this needs?*
If yes — and you're reaching past its surface into its structure — that's where the method belongs.

## Rules

- Behaviour belongs with its data — move a method that loops or queries one other owned object onto that object.
  _Move the method onto the object (`$node->edges()`)._
- Ask the object directly; don't use its identity as a key to look its own fact up through a collaborator.
  _Move the lookup onto the object that owns the identity._

## Bad → good

```php
// Bad
public function suspend(Customer $customer, string $reason): void
{
    $customer->suspended = true;
    $customer->suspended_reason = $reason;
    $customer->save();
}

// Good
public function suspendByTelling(Customer $customer, string $reason): void
{
    $customer->suspend($reason);
}
```

```php
// Bad
public function forItem(CatalogItem $item): array
{
    return $this->registry->has($item->code)
        ? $this->registry->get($item->code)->reservedSkus
        : [];
}

// Good
public function forItemDirect(CatalogItem $item): array
{
    return $item->reservedSkus();
}
```

## When it fires

- Exiled behaviour / feature envy — a method operating on ONE other owned object's internals that belongs ON that object — `FeatureEnvyDetector`
- Indirect feature envy — a method that uses an owned object's IDENTITY as a key to look up a fact about it through a collaborator — `KeyedLookupEnvyDetector`

## Checklist

- [ ] Behaviour belongs with its data — move a method that loops or queries one other owned object onto that object.
- [ ] Ask the object directly; don't use its identity as a key to look its own fact up through a collaborator.

## Related skills

- [`backend/value-objects`](../value-objects/SKILL.md) — behaviour belongs on the typed object that owns the data.
- [`backend/fix-at-the-source`](../fix-at-the-source/SKILL.md) — move the method to where the data lives, not a downstream class that walks it.
