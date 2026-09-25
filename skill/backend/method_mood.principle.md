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