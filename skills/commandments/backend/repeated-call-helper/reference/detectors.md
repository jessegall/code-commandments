# Promote a repeated call to a method — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`repeated-guard`** — The SAME compound guard condition recurs in ≥2 places — the same check spelled differently (inline reaches vs locals) or reordered still counts, so a copied condition has no name — `RepeatedGuardDetector`
- **`repeated-named-call`** — The same `with`-style (variadic) method is called with the same named argument at 2+ sites, instead of a named helper on the type — `RepeatedNamedCallDetector`
- **`repeated-type-guard`** — The SAME multi-`instanceof` type-narrowing guard (`$x instanceof A && $x->y instanceof B`) is written verbatim in ≥2 places — a check with no name, copied instead of named — `RepeatedTypeGuardDetector`
