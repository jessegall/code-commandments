# Exceptions — fail hard, fix once — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`generic-exception`** — `throw new <bare SPL>` (RuntimeException/LogicException/…) instead of a named type — `GenericExceptionDetector`
- **`message-at-throw`** — Message string built at the throw site (no domain values / named factory) — `MessageAtThrowDetector`
- **`rethrown-unchanged`** — a `catch` whose whole body is `throw $e;` — the clause catches the failure, adds nothing to it and hands it back on, which is what writing no clause does — `RethrownUnchangedDetector`
- **`swallow-catch`** — `catch` whose only effect is `return null/false/[]`; empty catch (silent swallow) — `SwallowCatchDetector`
- **`wrapping-without-cause`** — Wrapping a caught exception without passing it as `previous`/cause — `WrappingWithoutCauseDetector`
