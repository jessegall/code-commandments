# Exceptions — fail loud, named, with the cause — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`generic-exception`** — A throw of the language's catch-all failure (`Exception`, `RuntimeException`, `Error`, `RuntimeError`) instead of a named one — `generic-exception`
- **`message-at-throw`** — A failure's message written as a string at the throw, not by a named constructor given the values involved — `message-at-throw`
- **`rethrown-unchanged`** — A `catch` whose whole body throws back the failure it caught: it adds nothing, which is what writing no clause does — `rethrown-unchanged`
- **`swallow-catch`** — A `catch` whose body is empty, or only returns `null`/`false`/an empty value or moves on: the failure becomes something that looks like an answer — `swallow-catch`
- **`wrapping-without-cause`** — A new failure thrown inside a `catch` without the caught one as its cause: the trace of where it started is lost — `wrapping-without-cause`
