# Python class layout — the inventory at the top — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`python-member-after-method`** — a constant, class attribute or field declared below a method — the class's state hidden among its behaviour — `MemberAfterMethodDetector`
