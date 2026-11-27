# Duplication — one piece of knowledge, one home — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`converted-argument`** — Most callers convert a value the same way for one parameter — `converted-argument`
- **`divergent-twin`** — Two functions share most of their uncommon steps, but one misses a step the other takes — `divergent-twin`
- **`duplicate-function`** — Two functions repeat the same body — `duplicate-function`
- **`list-mirrors-folder`** — A hand-written class list repeats every concrete class of one type in its folders — `list-mirrors-folder`
- **`near-duplicate-function`** — Two functions run the same code with different names or data — `near-duplicate-function`
- **`recast-result`** — Most callers convert what one of the project's methods returns the same way, instead of the method returning that type — `recast-result`
- **`repeated-folder-path`** — One folder is spelled out at three or more sites across separate scopes — `repeated-folder-path`
- **`repeated-guard`** — A compound condition about an object is written out at more than one site — `repeated-guard`
- **`repeated-named-call`** — The same `with`-style method is called with the same named argument at 2+ sites, instead of a named helper on the type. — `repeated-named-call`
- **`repeated-reach`** — One member of another object is reached through (`$request->headers->get(…)`) at more than one site, instead of a method that names each value it reads
- **`repeated-record-shape`** — One call is handed a keyed array of the same keys, with a part of the same values, at more than one site, instead of a named constructor on the type it fills — `repeated-record-shape`
- **`repeated-static-factory`** — One function asks a static factory for the same object twice, with the same arguments, and asks each one something, instead of holding the object and asking it — `repeated-static-factory`
- **`repeated-type-guard`** — The same compound type check is written out at more than one site — `repeated-type-guard`
- **`unnamed-vocabulary-literal`** — A string literal fills a parameter that already has a named constant for that value — `unnamed-vocabulary-literal`
