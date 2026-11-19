# One way to the server: generated routes and one client — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`hard-coded-server-url`** — A string written out as the path of a route the server declares by name — `HardCodedServerURLDetector`
- **`raw-fetch-in-component`** — A Vue component calls the global `fetch` itself instead of the one client module — `RawFetchInComponentDetector`
