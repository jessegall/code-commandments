# Class layout — state first, then behaviour — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`member-after-method`** — A trait use, constant, property, property hook or enum case declared BELOW a method — state a reader only meets after the behaviour that uses it — `MemberAfterMethodDetector`
- **`member-out-of-order`** — A declaration in the head of a class that arrives after something belonging below it — a constant under a property, a public field under a private one, a hook above the fields it reads — `MemberOutOfOrderDetector`
