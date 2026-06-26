---
name: tell-dont-ask
description: Behaviour belongs with the data it operates on (feature envy, Fowler). If a method reaches through ONE other object's internal structure — looping its collection, walking its tree of parts — to work out something the object should answer itself, that logic is exiled from its home; Move the Method onto the object (`$node->edges()`, not `EdgeDetector::detect($node)`). Read this BEFORE you write a `*Detector`/`*Walker`/`*Finder` that iterates one object's collection from the outside. NOTE the exception: a policy/Strategy over the object's flat scalar fields (a grade, a label, a classification) is NOT envy.
---

# Tell, don't ask — behaviour belongs with its data

> An object that holds the data to answer a question should answer it. When the answer is computed
> somewhere else — a separate class reaching in to read its fields and derive a result — the behaviour
> has been **exiled** from its home. Move it back: `$node->edges()`, not `EdgeDetector::detect($node)`.

## The sin: exiled behaviour (feature envy)

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

A method that only reads the object's **flat scalar fields** to compute a value — a grade, a label, a
yes/no — is an **external policy**, not envy. That's a *Strategy*, the documented exception: scoring,
formatting, and classifying legitimately live in their own class, because the rule can vary independently of
the data. Reading fields is fine; *operating on the object's internals* is the sin.

**Why it's a sin:**

- The knowledge is **split from the data it's about** — they drift apart and must be hand-synced.
- Every caller must route through the external helper and hand it the object, instead of just **asking
  the object**.
- The object goes **anemic** — it carries the data, but the behaviour that defines it lives elsewhere.

```php
// Bad — a node's edges are intrinsic node knowledge, exiled into a separate detector
final class WorkflowNodeEdgeDetector
{
    public function detect(WorkflowNode $node): array
    {
        $edges = [];
        foreach ($node->getOutputs() as $output) {
            foreach ($node->getConnections() as $connection) {
                if ($connection->fromPort() === $output->name()) {
                    $edges[] = $connection->target();
                }
            }
        }
        return $edges;          // every read is $node->… ; nothing of $this
    }
}

// callers
$edges = $this->edgeDetector->detect($node);
```

```php
// Good — the knowledge lives where the data lives
final class WorkflowNode
{
    public function edges(): array
    {
        $edges = [];
        foreach ($this->outputs as $output) {
            foreach ($this->connections as $connection) {
                if ($connection->fromPort() === $output->name) {
                    $edges[] = $connection->target;
                }
            }
        }
        return $edges;
    }
}

// callers just ask
$edges = $node->edges();
```

## The fix

Move the computation onto the object whose data it consumes. The external method delegates to it
(`return $node->edges();`) or disappears. If the external class has nothing left, delete it.

## What is NOT this sin

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

## The tell

The smell is a **loop over another object's collection** (or a recursive walk of its parts) inside a class
that isn't that object — `foreach ($line->children …)`, `containsAggregate($block->left)`. It's working the
object's internals from the outside. Ask: *does the object I'm walking already hold everything this needs?*
If yes — and you're reaching past its surface into its structure — that's where the method belongs.
