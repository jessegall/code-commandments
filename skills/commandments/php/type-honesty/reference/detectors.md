# Type honesty — the type must not lie — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`filled-parameter`** — a `void` method that writes a property of an object it was handed, whose caller then reads what it handed over — the signature says the object goes in and nothing says it comes out changed — `filled-parameter`
- **`impossible-guard`** — `if ($order === null)` where `$order` is typed `Order`, with no `?` — one of the two is wrong, and both are worth knowing — `impossible-guard`
- **`refused-bequest`** — A method that overrides or implements an inherited one only to throw: the class claims a contract it refuses to keep. — `refused-bequest`
- **`scratch-state-restore`** — Scratch state on the object: a method saves one of its own fields to a local and restores it later, the field really a per-call input. — `scratch-state-restore`
- **`unused-parameter`** — A parameter of a method no subclass can override that its body never reads: every caller must pass a value nothing uses. — `unused-parameter`
- **`unused-private-member`** — A private method nothing in its class calls, or a private property nothing in its class reads: dead code the class still carries. — `unused-private-member`
- **`useless-property-hook`** — A property's getter that reads nothing from the object: a stored property wearing computed syntax. — `useless-property-hook`
