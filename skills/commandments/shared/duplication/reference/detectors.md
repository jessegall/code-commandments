# Duplication: give repeated code one owner — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`converted-argument`** — Most callers convert a value the same way for one parameter — `converted-argument`
- **`divergent-twin`** — Two functions share most of their uncommon steps, but one misses a step the other takes — `divergent-twin`
- **`duplicate-function`** — Two functions repeat the same body — `duplicate-function`
- **`list-mirrors-folder`** — A hand-written class list repeats every concrete class of one type in its folders — `list-mirrors-folder`
- **`near-duplicate-function`** — Two functions run the same code with different names or data — `near-duplicate-function`
- **`repeated-folder-path`** — One folder is spelled out at three or more sites across separate scopes — `repeated-folder-path`
- **`repeated-guard`** — A compound condition about an object is written out at more than one site — `repeated-guard`
- **`repeated-type-guard`** — The same compound type check is written out at more than one site — `repeated-type-guard`
- **`unnamed-vocabulary-literal`** — A string literal fills a parameter that already has a named constant for that value — `unnamed-vocabulary-literal`
