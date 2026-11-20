# Release checklist

1. The tests of every package you touched are green in the dev container (`scripts/dev go test …`).
2. New or changed detector or sin? Each registers itself in `init()`; nothing to add to a list. A draft marked
   `Unpublished()` stays out of every catalog until you remove the method.
3. The generated documents are current: the pre-commit hook regenerates them; `composer sins` by hand.
4. Fix every finding on the files you touched.
5. Commit on main — no attribution trailer.
6. A release build can be tried locally without publishing anything:
   `scripts/dev scripts/release/build v0.0.0-try dist --tool-only` (the Go binaries, checked against their budgets).
7. Bump the journal plugin, regenerate the manifests, commit and push main. Dispatch
   `gh workflow run release.yml --ref main -f version=vX.Y.Z -f commit="$(git rev-parse HEAD)"`.
8. Wait for the workflow to finish. Verify that the build succeeded before the public tag was created, that the
   tag points to the requested commit, and that the published assets match `SHA256SUMS`. For a plugin-only release,
   verify the tag and the pinned release's assets. Upgrade the plugin once those checks pass.
