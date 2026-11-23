# Documentation — say only what the code cannot — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`archaeology-comment`** — A comment narrates the history of the code — `archaeology-comment`
- **`bloated-docblock`** — A type has several paragraphs of description — `bloated-docblock`
- **`ceremony-docblock`** — A doc comment repeats the function signature — `ceremony-docblock`
- **`dangling-doc-reference`** — A document reference points at a declaration the project does not have — `dangling-doc-reference`
- **`inline-docblock`** — A docblock whose delimiter shares a line with its text — a one-liner, or a block that opens or closes next to content — `inline-docblock`
- **`negative-space-comment`** — A comment defends the code against an assumption the reader has no reason to make — `negative-space-comment`
- **`redundant-arrow-return-type`** — An arrow repeats the type already inferred from its expression — `redundant-arrow-return-type`
- **`restated-comment`** — A comment repeats the words of the statement it documents — `restated-comment`
- **`stacked-docblock`** — Several doc comments describe one declaration — `stacked-docblock`
