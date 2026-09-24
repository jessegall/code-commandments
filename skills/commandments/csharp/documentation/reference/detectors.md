# C# documentation — short, present tense, rare — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`csharp-archaeology-comment`** — a comment that tells the code's past — `// formerly lived in CheckoutService`, `// refactored to use the cache` — describing a version nobody is reading — `ArchaeologyCommentDetector`
- **`csharp-bloated-docblock`** — a type whose doc comment runs to two or more paragraphs — usually a sign the type does too much — `BloatedDocblockDetector`
- **`csharp-ceremony-docblock`** — a doc comment whose every tag is empty or only repeats the signature — `<param name="order">The order.</param>`, an empty `<returns>` — `CeremonyDocblockDetector`
- **`csharp-dangling-doc-reference`** — a `<see cref>` that resolves to nothing from where it is written — a name the project no longer declares, or one spelled so it does not reach it — `DanglingDocReferenceDetector`
- **`csharp-negative-space-comment`** — a comment defending the code against a reading nobody made — `// not magic, just a day`, `// deliberately not sorted` — saying what it is not instead of what it is — `NegativeSpaceCommentDetector`
