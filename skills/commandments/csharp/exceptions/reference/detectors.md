# C# exceptions — fail loud, named, at the source — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`csharp-generic-throw`** — `throw new Exception/InvalidOperationException("…")` — a failure that names nothing, described in prose at the throw site — `GenericThrowDetector`
- **`csharp-swallowed-exception`** — A bare `catch` or `catch (Exception)` whose body is empty, continues, or returns nothing — every failure, expected or not, made to vanish — `SwallowedExceptionDetector`
- **`csharp-wrapping-without-cause`** — a `catch` that throws a new exception without passing the caught one as its inner exception, so the original stack trace is lost — `WrappingWithoutCauseDetector`
