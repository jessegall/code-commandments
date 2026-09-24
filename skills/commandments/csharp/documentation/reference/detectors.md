# C# documentation — short, present tense, rare — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`csharp-archaeology-comment`** — a comment that tells the code's past — `// formerly lived in CheckoutService`, `// refactored to use the cache` — describing a version nobody is reading — `ArchaeologyCommentDetector`
