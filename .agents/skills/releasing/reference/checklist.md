# Release checklist

1. The tests of every package you touched are green in the dev container (`scripts/dev go test …`).
2. New or changed detector or sin? Each registers itself in `init()`; nothing to add to a list. A draft marked
   `Unpublished()` stays out of every catalog until you remove the method.
3. The generated documents are current: the pre-commit hook regenerates them; `composer sins` by hand.
4. Fix every finding on the files you touched.
5. Commit on main — no attribution trailer.
6. A release build can be tried locally without publishing anything:
   `scripts/dev scripts/release/build v0.0.0-try dist --tool-only` (the Go binaries, checked against their budgets).
7. Bump the journal plugin, tag `vX.Y.Z` and push main and the tag, without asking: the tag runs the release
   workflow, and the plugin is upgraded once it has run. Never the full suite — the tests of what the release
   carries are its gate.
