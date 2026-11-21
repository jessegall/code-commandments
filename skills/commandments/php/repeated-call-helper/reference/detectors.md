# Promote a repeated call to a method — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`repeated-named-call`** — The same `with`-style (variadic) method is called with the same named argument at 2+ sites, instead of a named helper on the type — `repeated-named-call`
- **`repeated-query-chain`** — One Eloquent query chain written alike at three or more sites outside its model — `repeated-query-chain`
