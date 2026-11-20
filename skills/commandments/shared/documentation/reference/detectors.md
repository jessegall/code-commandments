# Documentation: explain the code as it is — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`archaeology-comment`** — A comment narrates the history of the code — `ArchaeologyCommentDetector`
- **`bloated-docblock`** — A type has several paragraphs of description — `BloatedDocblockDetector`
- **`ceremony-docblock`** — A doc comment repeats the function signature — `CeremonyDocblockDetector`
- **`dangling-doc-reference`** — A document reference points at a declaration the project does not have — `DanglingDocReferenceDetector`
- **`negative-space-comment`** — A comment defends the code against an assumption the reader has no reason to make — `NegativeSpaceCommentDetector`
- **`restated-comment`** — A comment repeats the words of the statement it documents — `RestatedCommentDetector`
- **`stacked-docblock`** — Several doc comments describe one declaration — `StackedDocblockDetector`
