# orchestrator-hooks

**The prerequisite for everything below it**, which is why it is first. A project can
reach an orchestration moment today only by writing a raw harness hook and
reverse-engineering the domain from tool calls. Harness events are the TRANSPORT;
these are the DOMAIN.

## Open

- A domain event layer a project ties into. **A class declares its event by
  type-hinting `handle()`** — the reflection `Config::configure` and
  `Query::where(fn (LaravelNode $n) => …)` already use, so there is no `handles()`
  list to drift.
- A `Verdict` maps onto what `HookResponse` already expresses — pass, note, quietly,
  carry, refuse.
- **An event may be VETOED only where it derives from a `PreToolUse` in the process
  about to act.** Anything from `PostToolUse`, `Stop` or a reaper is already true by
  the time a handler sees it, and a veto there would be theatre.
- A `refuse()` from a non-veto event is DEMOTED, never dropped: a quiet note plus a
  condition recorded on the item, so it bites at the next gate that genuinely can
  refuse. That is `WriteGate`'s own move for a shell write that already landed.
- Containment: one bad project class has already silenced every hook in a process, so
  a throw is `pass()` for that handler alone, an overrun quarantines on the second,
  and the project finds out three ways. **Silence is the bug.**
