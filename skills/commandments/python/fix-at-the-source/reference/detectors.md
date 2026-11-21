# Python fix at the source — where a value is born, not where it hurts — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`python-blocked-await`** — `time.sleep`, `requests.get` or `subprocess.run` inside an `async def` — the thread it stops is the event loop, so every coroutine waiting on it stops too — `python-blocked-await`
- **`python-frozen-default`** — a parameter defaulted to `datetime.now()`, `uuid4()` or another call that answers differently each time — it runs once when the def is defined, so every call that omits it gets the moment the module was imported — `python-frozen-default`
- **`python-mutable-default`** — a parameter defaulted to a `[]`, `{}` or `set()` — one container, made once, shared by every call that omits it — `python-mutable-default`
