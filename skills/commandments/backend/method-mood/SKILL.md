---
name: commandments-backend-method-mood
description: What GRAMMAR a method name is written in: a command is an imperative (`hide()`, `openFor()`, `write()`), never a third-person narration (`hides()`, `opensFor()`, `writes()`); a method that answers `bool` about its own state wears a question (`isShown()`, `hasParent()`, `awaitsAnswer()`). Read this when you name or rename a method, and when a naming sin points here.
---

# Method mood — an order, or a question

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> You do not describe an object to itself. A method you CALL is an order — `hide()`, not
> `hides()` — and a method that answers about state is a question — `isHidden()`, not `binds()`. Two
> moods, and the call site tells you which one you are writing.

## The principle

Read a call out loud. `$panel->hide()` is an instruction you give an object: do this. `$panel->hides()`
is a sentence ABOUT the object, narrated by nobody, to nobody — it reads as documentation that wandered
into the code. The imperative is not a style preference; it is what a call IS. You are not describing
behaviour, you are demanding it.

The exception is a method that answers rather than acts, and it gets the other mood: a question. A
`bool` about the object's own state is `isShown()`, `hasParent()`, `canRetry()`, `awaitsAnswer()` — so
that `if ($el->isShown())` reads as the question it is. A bare `if ($el->shows())` reads as a claim, and
the reader has to stop and work out whether the call changes anything.

### Where the line falls

A predicate that asks about a RELATION — one thing against another — is already a sentence with a
subject and an object, and the third person is correct English for it: `$set->contains($item)`,
`$pattern->matches($name)`, `$range->covers($date)`. The tell is the argument: something is being
compared to something. A predicate with no argument has no second party, so it can only be describing
the receiver — and describing the receiver is what a question is for.

So:

- **acts** (returns nothing, or returns itself) → imperative: `hide()`, `enterTestMode()`, `openFor($user)`
- **answers about itself** (`bool`, no argument) → question: `isShown()`, `hasParent()`, `canRetry()`
- **answers about a relation** (`bool`, takes what it is compared with) → third person is fine:
  `contains($item)`, `matches($name)`, `covers($date)`

### What is never renamed

A name you did not choose is not a sin: a method declared by a parent class or an interface — yours or
a framework's — keeps the contract's spelling. `offsetExists()` is `ArrayAccess`'s word, not yours, and
a magic method is the language's.

### Not judged from the name alone

Only a verb the rule KNOWS is judged, so a plural-noun getter is never mistaken for narration:
`names()`, `bindings()`, `fields()` and `arguments()` are nouns, and an imperative that merely ends in
an `s` (`process()`, `pass()`, `dismiss()`, `focus()`) is left alone. When in doubt the rule says
nothing — a missed narration costs a reader a moment, a false one costs them their trust.

## Rules

- A `bool` answering about the object itself wears a question: `isBound()`, `isSpinning()`, `hasParent()`, `awaitsAnswer()`. (A predicate that takes what it compares against — `contains(\$item)`, `matches(\$name)` — is already a sentence and stays as it is.)
  _make it a question: `is…`, `has…`, `can…`, `awaits…`_
- Name a command in the imperative: `hide()`, `enterTestMode()`, `openFor(\$user)` — never the third-person `hides()`, and never a participle.
  _drop the -s: the call site is giving the order, not narrating it_

## Bad → good

### bare-state-predicate

A `bool` about the object's own state named as a bare verb — `binds()`, `spins()` — where a question belongs

```php
// Bad
/**
 * A bool about the line itself, named as a claim instead of a question.
 */
public function reports(): bool
{
    return $this->level === 'error';
}

// Good
/**
 * The FIX is the NAME: same body, same class, asked as a question. `if ($line->isErrored())`
 * reads as English at the call site, where `if ($line->reports())` reads as a claim.
 */
public function isErrored(): bool
{
    return $this->level === 'error';
}
```

### narrated-command

A command named in the third person — `hides()`, `entersTestMode()` — where a call is an order, not a description of one

```php
// Bad
/**
 * The fluent form of the same mistake: chainable, still an order.
 */
public function clears(): static
{
    $this->entries = [];

    return $this;
}

// Good
/**
 * The FIX is the NAME: drop the -s. Still fluent, still the same body — but `$weights->clear()`
 * is the order the call site is actually giving, instead of a description of one.
 */
public function clear(): static
{
    $this->entries = [];

    return $this;
}
```

## When it fires

- A `bool` about the object's own state named as a bare verb — `binds()`, `spins()` — where a question belongs — `BareStatePredicateDetector`
- A command named in the third person — `hides()`, `entersTestMode()` — where a call is an order, not a description of one — `NarratedCommandDetector`

## Checklist

- [ ] A `bool` answering about the object itself wears a question: `isBound()`, `isSpinning()`, `hasParent()`, `awaitsAnswer()`. (A predicate that takes what it compares against — `contains(\$item)`, `matches(\$name)` — is already a sentence and stays as it is.)
- [ ] Name a command in the imperative: `hide()`, `enterTestMode()`, `openFor(\$user)` — never the third-person `hides()`, and never a participle.

## Related skills

- [`backend/role-vocabulary`](../role-vocabulary/SKILL.md) — what a class is called and what that name promises; this is the same contract one scale down.
- [`backend/tell-dont-ask`](../tell-dont-ask/SKILL.md) — an order is the point: if you find yourself narrating what an object does, you are probably reaching into it.
