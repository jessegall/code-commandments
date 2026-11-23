---
name: commandments-shared-fix-at-the-source
description: "Find the root cause before changing anything: trace a value to where it is born and fix it THERE, never patch the symptom. Read this FIRST whenever you are asked to refactor, fix, clean up, improve or review any code, in any language, and specifically before adding a null check, a `?? default`, a cast, a wrapper or a try/catch, making a field nullable, exempting a finding, tolerating bad input downstream, or when the same value gets re-checked in many places. Also before a constructor that does work, a static field that is written, or blocking on asynchronous work."
---

# Fix at the source

> 🔱 **The rule above all — apply it ALWAYS.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This is that rule.

> Every problem has a place where it starts. Fix it there, never where it shows.
> Read this FIRST whenever you are asked to fix, refactor, clean up or review code, and before you add a null check,
> a `?? default`, a cast, a wrapper or a `try`/`catch` to make a finding go quiet. Every other skill defers to this one.
> The examples are pseudocode: read them as the language you are writing.
> An arrow in an example (`←` or `↑`) marks the line the lesson is about.

## The principle

**A finding is a symptom. Walk back to where the bad value, missing type or wrong shape is born, and fix it there.**

The place a problem surfaces is almost never the place it was made. A null check in a consumer, a default at a read,
a nullable field "to be safe", a suppressed finding: each one makes the symptom quiet and leaves the cause in place, so
the next consumer needs the same patch. The code grows and the types lie more with every round.

Before you change a line:

1. **Name the symptom.** What are you about to write: a null check, a default, a nullable field, a second copy of a
   validation, an exemption?
2. **Find where the value is born.** Walk upstream: the boundary that parsed it, the method that returned it, the
   constructor that built it. Keep going for as long as it stays loose.
3. **Ask at the origin: could this have been made certain here?** Could the boundary have thrown on the missing
   field, or parsed into a type where the case you are guarding cannot exist?
4. **Fix it there, and delete the symptom.** The downstream checks have nothing left to guard.
5. **If the honest fix touches many call sites, touch them.** That breadth is the bug surfacing, not a reason to
   stop it at one boundary.

A value stays optional only when it is optional in the domain, decided at its source. "Optional" is never a way to
make a check go quiet.

```pseudo
// Bad: the decoder checks nothing, so every handler defends itself
class RawCommand {
    kind: string?
    target: string?
    label: string?
}

function move(command: RawCommand) {
    if (command.target == null) { return }   // ← every handler repeats this check

    board.move(command.target, command.label ?? "")
}

// Good: the boundary makes the value certain, once
function decode(payload): MoveCommand {
    return MoveCommand(
        target: payload["target"] ?? throw CommandWithoutTarget.in(payload),   // ← decided here, once
        label: Label.from(payload["label"]),
    )
}

function move(command: MoveCommand) {
    board.move(command.target, command.label)
}
```

## The sins

These are the same idea at the level of a class: state and effects that have no clear origin, so their problems can
only be patched where they show.

### constructor-side-effect

A constructor that calls out to a collaborator and throws the result away. Merely creating the object now changes
something outside it, and nobody reading `new` expects that. A constructor establishes what the object is; the work
happens in a method someone calls.

```pseudo
// Bad
class Importer {
    constructor(queue: Queue) {
        queue.clear()   // ← creating an Importer empties the queue
    }
}

// Good
class Importer {
    constructor(queue: Queue) {
        this.queue = queue
    }

    function start() {
        this.queue.clear()
    }
}
```

### mutable-static-state

A write to a static field: a global variable with a namespace attached. Whichever write happens last wins, so the
order code runs in changes the result, and no signature shows who depends on it. Hold changing state on an instance
that someone owns and passes.

```pseudo
// Bad
class Tenant {
    static current: Tenant

    static function switchTo(tenant) { Tenant.current = tenant }   // ← whoever writes last wins
}

report = Report.for(Tenant.current)

// Good
class TenantContext {
    constructor(tenant: Tenant) { this.tenant = tenant }
}

class ReportBuilder {
    constructor(context: TenantContext) { this.context = context }
}
```

### unread-collaborator

A collaborator the constructor stores in a private field that no method ever reads. The class asks for something it
does not use, and every caller still has to supply it. Delete the parameter and the field, then follow the callers
and delete what they built only to hand it over. If the class was meant to use it, the empty field is pointing at
the real bug.

```pseudo
// Bad
class InvoiceMailer {
    constructor(mailer: Mailer, clock: Clock) {
        this.mailer = mailer
        this.clock = clock          // ← never read
    }

    function send(invoice) { this.mailer.send(invoice.asMail()) }
}

// Good
class InvoiceMailer {
    constructor(mailer: Mailer) {
        this.mailer = mailer
    }
}
```

### csharp-blocked-await — in C#

`.Result`, `.Wait()` or `.GetAwaiter().GetResult()` on a task. It blocks the thread the work needs back, so it
deadlocks under load and nowhere else. `await` it and make the caller async, however far up that goes: stopping at
one boundary with `.Result` is the symptom patch.

```csharp
// Bad
public Invoice Load(int id)
{
    return repository.FindAsync(id).Result;   // ← blocks the thread the task needs back
}

// Good
public async Task<Invoice> Load(int id)
{
    return await repository.FindAsync(id);
}
```

### python-blocked-await — in Python

`time.sleep`, `requests.get` or `subprocess.run` inside an `async def`. The thread it stops is the event loop, so
every coroutine waiting on it stops too.

```python
# Bad
async def poll(url: str) -> Response:
    time.sleep(1)   # ← stops the event loop for every coroutine
    return requests.get(url)

# Good
async def poll(client: AsyncClient, url: str) -> Response:
    await asyncio.sleep(1)
    return await client.get(url)
```

### python-mutable-default · python-frozen-default — in Python

A parameter defaulted to a container (`[]`, `{}`, `set()`) or to a call that answers differently each time
(`datetime.now()`, `uuid4()`). A default is evaluated once, when the `def` is defined: every call that omits it
shares one list, or gets the moment the module was imported.

```python
# Bad
def add_line(line: Line, lines: list[Line] = []) -> list[Line]:   # ← one list, shared by every call
    lines.append(line)
    return lines

def stamp(order: Order, at: datetime = datetime.now()) -> Order: ...
# ↑ worked out once, when the module is imported

# Good
def add_line(line: Line, lines: list[Line] | None = None) -> list[Line]:
    lines = [] if lines is None else lines
    lines.append(line)
    return lines

def stamp(order: Order, at: datetime | None = None) -> Order:
    at = at or datetime.now()
    ...
```

## In your language

- **PHP.** The symptom patches are `??`, `?->`, a cast, `@`, and making a property nullable. Static state is a
  `static` property written from a method.
- **Python.** The patches are `x or default`, `getattr(x, "y", None)`, `# type: ignore` and a bare `except`. Static
  state is a module-level variable written through `global`, or a class attribute written from a method.
- **C#.** The patches are `!`, `?.`, `?? default` and `#pragma warning disable`.
- **TypeScript.** The patches are `!`, `as`, `?.` and `// @ts-ignore`.
- **Go.** The patches are a dropped `err` and a zero value read as data. Static state is a package-level variable
  written after start-up.

## Commands

- `{{binary}} judge --skill=shared/fix-at-the-source` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `constructor-side-effect`, `csharp-blocked-await`, `mutable-static-state`, `python-blocked-await`, `python-frozen-default`, `python-mutable-default`, `unread-collaborator`.
- `{{binary}} report --detector=<Detector> --reason="…" --best-design="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`shared/absence`](../absence/SKILL.md) — the four honest answers when a value really can be missing.
- [`shared/type-honesty`](../type-honesty/SKILL.md) — a declaration that agrees with what the source produces.
