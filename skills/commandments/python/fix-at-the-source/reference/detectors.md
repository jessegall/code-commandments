# Python fix at the source — where a value is born, not where it hurts — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`python-blocked-await`** — `time.sleep`, `requests.get` or `subprocess.run` inside an `async def` — the thread it stops is the event loop, so every coroutine waiting on it stops too — `BlockedAwaitDetector`
- **`python-constructor-side-effect`** — an `__init__` that tells a collaborator to act and throws the answer away — merely building the object has an effect outside it. — `ConstructorSideEffectDetector`
- **`python-divergent-twin`** — two functions do the same job, but one of them skips a step the other takes — usually a fix made in one copy and forgotten in the other. — `DivergentTwinDetector`
- **`python-frozen-default`** — a parameter defaulted to `datetime.now()`, `uuid4()` or another call that answers differently each time — it runs once when the def is defined, so every call that omits it gets the moment the module was imported — `FrozenDefaultDetector`
- **`python-mutable-default`** — a parameter defaulted to a `[]`, `{}` or `set()` — one container, made once, shared by every call that omits it — `MutableDefaultDetector`
- **`python-mutable-static-state`** — a `global` written from a function, or a class attribute set from a method — state no instance owns, changed by whoever ran last — `MutableStaticStateDetector`
- **`python-unread-collaborator`** — a collaborator `__init__` keeps as an attribute of its own, `self._mailer = mailer`, that nothing anywhere reads — the class asks for something it does not use — `UnreadCollaboratorDetector`
