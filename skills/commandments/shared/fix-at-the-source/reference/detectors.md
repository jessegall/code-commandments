# Fix at the source — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`constructor-side-effect`** — A constructor that performs a side effect on a collaborator and throws away the result, so simply creating the object changes something outside it. — `constructor-side-effect`
- **`csharp-blocked-await`** — `.Result`, `.Wait()` or `.GetAwaiter().GetResult()` on a task — the thread it blocks is the one the work needed back, so it deadlocks under load and nowhere else — `csharp-blocked-await`
- **`mutable-static-state`** — A write to a static property — really a global variable with a namespace attached — where whichever write happens last wins, so the order code runs in changes the result. — `mutable-static-state`
- **`python-blocked-await`** — `time.sleep`, `requests.get` or `subprocess.run` inside an `async def` — the thread it stops is the event loop, so every coroutine waiting on it stops too — `python-blocked-await`
- **`python-frozen-default`** — a parameter defaulted to `datetime.now()`, `uuid4()` or another call that answers differently each time — it runs once when the def is defined, so every call that omits it gets the moment the module was imported — `python-frozen-default`
- **`python-mutable-default`** — a parameter defaulted to a `[]`, `{}` or `set()` — one container, made once, shared by every call that omits it — `python-mutable-default`
- **`unread-collaborator`** — a collaborator promoted into a private field by the constructor and never read anywhere in the class — the class asks for something it does not use, and only a search of the whole type says so — `unread-collaborator`
