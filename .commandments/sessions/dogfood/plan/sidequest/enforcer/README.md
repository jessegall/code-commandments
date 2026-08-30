# enforcer

**Jesse's design.** A role that checks the orchestrator itself is not drifting out of
sync — and the thing that makes it different from every other role is **who dispatches
it.**

## The distinction: a dispatched agent and a HIDDEN one

Every role so far is dispatched BY the orchestrator, and reports back TO it. That
works because the orchestrator asked, and is therefore listening.

**The enforcer is dispatched by the SYSTEM.** Nobody asked for it. It fires in the
background, sometimes, precisely when the orchestrator would not have thought to run
it — which is the only moment a drift check is worth anything, since an orchestrator
that knows it is out of sync is not the one that needs checking.

**It is a boolean on the role**: dispatched-by-the-orchestrator, or dispatched-by-the
system. One flag, and everything else about a role is unchanged.

## Which means it needs a channel BACK that no other role needs

A worker returns its report to whoever dispatched it. **The enforcer has no such
caller** — the system spawned it, and the system is not who needs to hear the answer.

So it reaches INTO the orchestrator's session. Verified available:

```
claude -r <session-id> -p "<what it found>"      resume that session and speak
claude -c -p "<what it found>"                    the most recent in this directory
```

**That is the whole mechanism**, and it works because a session is addressable by id —
which is also why naming a session mattered more than it looked.

## When it fires: the orchestrator is WAITING

The moment is not a clock. It is **the orchestrator having dispatched and gone quiet
while workers are still running** — which the board already knows: a `Stop` with live
claims on it.

That is the honest window for a drift check, for the same reason the stop gate's
wait-nudge was: **the orchestrator is blocked anyway**, so the check costs nothing it
was going to spend. A drift check that interrupted real work would be the accelerant
shape all over again.

## What it should look for

Drift is the board and reality disagreeing, and every instance tonight was one of
these:

- **A worker reported and was never answered.** Twenty-five minutes, on the line
  deliberately surfaced first.
- **The board says `working` on something that landed**, or `reported` on a worker
  that is gone.
- **The root moved without the lanes**, so a lane is judging by old rules and
  answering confidently about new ones.
- **An item at round three with no acceptance** — usually a mis-specified task rather
  than a failing worker.

## What it must never do

**Never act.** It reports; the orchestrator decides. A system-dispatched agent that
also settled items would be making decisions nobody asked it for, which is exactly
the authority a hidden agent must not have.

**Never fire while the orchestrator is working.** Waiting is the window.

**Never state a fact it did not measure.** It is checking for a record that disagrees
with reality, so a check that guesses would be the disease presenting as the cure.
