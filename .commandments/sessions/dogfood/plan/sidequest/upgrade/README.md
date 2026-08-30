# upgrade

**Jesse's request**, and the evidence for it is two of the worst errors on a real
build.

A project should never reach for composer to move this package. What must actually
be run today is three steps, and the third is neither optional nor discoverable:

```
composer update jessegall/code-commandments --no-interaction
vendor/bin/commandments sync
bash scripts/worktree-lane.sh .lanes/<each>      # every lane — the forgotten one
```

**A lane keeps its own copied `vendor/`, so a root update leaves every lane behind.**
And a stale lane binary does not merely judge by old rules — **it answers questions
about the new ones wrongly, and confidently.**

## What it cost, measured

An anchor fix was reported broken **twice**, both probes having run
`vendor/bin/commandments` after a `cd` into a lane, executing the lane's older
binary. One was called "decisive". It sent this project chasing a bug that was
already fixed.

And a fresh integrator was briefed "all four checkouts on v4.284.0" — the lanes had
been refreshed, then the root updated, and never re-refreshed. **The orchestrator
walked into the lane-version trap inside the briefing where it was warning about
it.**

`lane list` diagnoses that drift and nothing repairs it. **A tool that can tell you
you are broken but not fix it is half a tool**, and the missing half is the one that
removes the requirement to remember.

## The shape

```
commandments upgrade              move the package, sync, and bring every lane with it
commandments upgrade --check      what is installed, what is available, what is behind
```

Reporting per lane, in the `lane list` format, because that is the moment the table
is most wanted.

## Four details, each paid for

**`--check` earns its place alone.** "Described as shipped" and "installable" are
different sentences, and they came apart twice — `lane list` at v4.281.1, and session
naming. Naming both numbers settles it in one word.

**It must REFUSE a lane whose worker is live.** Swapping `vendor/` under a running
builder makes a spurious gate failure indistinguishable from a real bug in its work.
**The board already knows which lanes are held**, so `upgrade` skips those, names
them, and says to run it again when they report. The one place it should do less
than asked.

**It must not silently overwrite project edits.** `sync` once deleted a project's own
gitignore exception. That is fixed — but an `upgrade` that wraps `sync` inherits the
whole class, so the guarantee is STATED in its output rather than assumed.

**The lane step belongs to the project.** One project's is a script claiming a port
block, seeding a database and linking `node_modules` — none of which this package
should know. **The profile already has `lane.sh`**, and that is the hook: `upgrade`
re-runs it per lane, and the project decides what it does.

## And `sync` is implied

Nobody has ever wanted the package moved without it, and forgetting it leaves the
skills stale while the binary is new — the same drift as a lane, one directory up.
`upgrade --no-sync` for the case that has not appeared yet.
