# Python documentation — concise, present-tense, rare — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`python-archaeology-comment`** — a comment or docstring narrating the code's history — where it lived, what it replaced, what it no longer is — `ArchaeologyCommentDetector`
- **`python-bloated-docblock`** — a class docstring of two or more paragraphs of prose — an essay that says the class does too much — `BloatedDocblockDetector`
- **`python-ceremony-docblock`** — a docstring with no summary whose every entry restates the annotated signature — `order (Order):`, `:rtype: int` — `CeremonyDocblockDetector`
- **`python-dangling-doc-reference`** — a Sphinx cross-reference in a docstring (`:class:`shop.cart.Basket``) to a first-party name the codebase no longer declares — `DanglingDocReferenceDetector`
- **`python-negative-space-comment`** — a comment or docstring defending the code against a reading nobody made — what it is not, rather than what it is — `NegativeSpaceCommentDetector`
