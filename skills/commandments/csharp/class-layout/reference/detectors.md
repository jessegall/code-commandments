# C# class layout — what the object holds, first — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`csharp-member-after-method`** — a field, constant or stored property declared below a constructor or a method — the type's state hidden among its behaviour — `MemberAfterMethodDetector`
- **`csharp-member-out-of-order`** — a `const` or `static readonly` value declared below a field or a stored property — the top of the type read in no particular order — `MemberOutOfOrderDetector`
