# C# repeated call helper — name what you keep writing — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`csharp-repeated-guard`** — the same compound condition — `order.Paid && !order.Cancelled` — written at two or more sites, a question with no name — `RepeatedGuardDetector`
- **`csharp-repeated-named-call`** — the same `with` copy — `order with { Status = OrderStatus.Shipped }` — written at two or more sites, an operation the record never named — `RepeatedNamedCallDetector`
- **`csharp-repeated-type-guard`** — the same chain of type checks — `node is Invocation call && call.Target is MemberAccess` — written at two or more sites, a shape with no name — `RepeatedTypeGuardDetector`
