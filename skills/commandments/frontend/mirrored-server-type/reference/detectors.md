# One source of truth for a server contract — generate the type, don't hand-copy it — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`mirrored-server-type`** — A hand-written TypeScript type mirrors a backend `Data` class one-to-one — two sources of truth for one contract that drift the moment the server shape changes — `MirroredServerTypeDetector`
