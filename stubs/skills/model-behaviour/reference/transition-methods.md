# Shaping the behaviour method

## Naming — let the transition pick the verb

| The write at the call site | The method it wants |
|---|---|
| `$m->seq = $m->seq + 1` / `$m->count += 1` / `$m->version++` | `incrementSequenceNumber()` / `advance…()` — a counter/accumulator step |
| `$m->status = Status::Shipped` (a closed-set value) | `markShipped()` / `transitionToShipped()` — a named state transition |
| `$m->status = Status::Cancelled` (with side effects) | `cancel()` — the domain verb, with the rule (`refund`, stamp `cancelled_at`) inside |
| Several fields set together before a save | one verb for the WHOLE transition (`verify()`, `publish()`, `close()`) |

The call site should read as a sentence: `$order->markShipped()`, not
`$order->status = OrderStatus::Shipped; $order->save();`.

## Should the method call `save()`? — default YES

**Default to self-persisting.** The behaviour method should mutate *and* persist:

```php
public function verify(): void
{
    $this->verified_at = now();
    $this->verification_token = null;
    $this->save();                  // the operation is complete on its own
}
```

If the method only mutates `$this` and leaves persistence to the caller, you have
not removed the smell — you have moved it one step: every caller still has to
remember `$model->verify(); $model->save();`, and one of them eventually won't.
The prophet flags a public model mutator that never calls `$this->save()` for
exactly this reason.

**The one exception — a deliberate unit-of-work.** When several mutations are
intentionally batched and persisted once inside an explicit transaction / aggregate
boundary, mutate-only methods are correct:

```php
DB::transaction(function () use ($order) {
    $order->markPaid();        // mutate-only, on purpose
    $order->assignWarehouse(); // mutate-only, on purpose
    $order->save();            // one save for the whole transition
});
```

That is the only time to leave a mutator unsaved — and it's an advisory, so absolve
those with a reason. What you must NEVER do is leave the mutation at the call site
*and* the rule on the model: that is the half-measure this skill exists to remove.

## Put the invariants WITH the transition

The reason to move the write is not tidiness — it is that a state change usually
has *rules* that must hold every time:

```php
public function ship(): void
{
    if ($this->status !== OrderStatus::Paid) {
        throw OrderNotShippable::because($this->status);
    }

    $this->status = OrderStatus::Shipped;
    $this->shipped_at = now();          // an invariant: a shipped order has a ship date
    $this->save();
}
```

When the write lives at the call site, every caller has to remember `shipped_at`
— and one of them eventually won't.

## What is genuinely fine to leave at the call site

The prophet is advisory, not a sin, because these legitimately exist:

- A **one-off administrative write** with no domain meaning and no duplication —
  a quick fix-up in a console command, a test factory tweak.
- A **migration / backfill script** mass-assigning columns.
- A truly **local, single-use** mutation that no other code performs.

If the write has no name worth giving it and appears exactly once, leave it.
The moment it acquires meaning or a second call site, extract it.
