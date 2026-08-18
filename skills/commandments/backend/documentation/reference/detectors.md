# Documentation — concise, present-tense, rare — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`archaeology-comment`** — History/archaeology comments ("formerly / used to be / refactored / no longer an X / was extracted") — `ArchaeologyCommentDetector`
- **`bloated-docblock`** — Multi-paragraph class docblock (class too big) — `BloatedDocblockDetector`
- **`ceremony-docblock`** — Docblock that only restates the typed signature (`@param Type $x`, no description) — `CeremonyDocblockDetector`
- **`dangling-doc-reference`** — A docblock `{@see}`/`{@link}` cross-references a FIRST-PARTY class that does not exist in the codebase — documentation pointing at a name that was renamed or removed, never at what the code actually is — `DanglingDocReferenceDetector`
- **`inline-docblock`** — A docblock whose delimiter shares a line with its text — a one-liner, or a block that opens or closes next to content — `InlineDocblockDetector`
- **`negative-space-comment`** — A comment defending the code against a strawman ("not random", "no magic", "not a coincidence", "not dead code") — `NegativeSpaceCommentDetector`
- **`restated-comment`** — An inline comment that only spells the statement below it back in prose ("// save the order" over `$this->orders->save($order)`) — `RestatedCommentDetector`
- **`stacked-docblock`** — Two or more docblocks stacked on one declaration — PHP reads only the last, so the ones above it are documentation nobody sees — `StackedDocblockDetector`
