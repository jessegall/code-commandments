### Read the call out loud

`panel.hide()` is an instruction you give an object: do this. `panel.hides()` is a sentence ABOUT the
object, narrated by nobody — it reads as documentation that wandered into the code. The imperative is not a
style preference; it is what a call IS.

### A question for state

A method or property that answers rather than acts gets the other mood: a question. A `bool` about the
object's own state is `is_shown`, `has_parent`, `can_retry`, `awaits_answer` — so `if el.is_shown:` reads
as the question it is. A bare `if el.shows():` reads as a claim, and the reader has to stop and work out
whether the call changes anything.

### Where the line falls

A predicate about a RELATION — one thing against another — is already a sentence with a subject and an
object, and the third person is right for it: `basket.contains(item)`, `pattern.matches(name)`,
`period.covers(day)`. The tell is the argument. With none, there is no second party, so it can only be
describing the receiver — and describing the receiver is what a question is for.

### What is never renamed

A name you did not choose is not a sin: a method overriding its base keeps the contract's spelling, and a
dunder is the language's. Only a verb the rule knows is judged, so a plural noun (`names`, `fields`) is
never mistaken for narration.